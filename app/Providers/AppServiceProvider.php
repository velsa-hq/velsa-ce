<?php

namespace App\Providers;

use App\Dashboard\DashboardTileRegistry;
use App\Dashboard\Tiles\BookingsByStatusTile;
use App\Dashboard\Tiles\KpiStripTile;
use App\Dashboard\Tiles\MyOpenLeadsTile;
use App\Dashboard\Tiles\MyUpcomingBookingsTile;
use App\Dashboard\Tiles\NeedsAttentionTile;
use App\Dashboard\Tiles\PastDueInvoicesTile;
use App\Dashboard\Tiles\PipelineByStageTile;
use App\Dashboard\Tiles\QuickLinksTile;
use App\Dashboard\Tiles\RecentActivityTile;
use App\Dashboard\Tiles\RevenueTrendTile;
use App\Dashboard\Tiles\TodayOutlineTile;
use App\Listeners\PostPaymentJournalEntries;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use App\Models\ReportDefinition;
use App\Observers\BookingObserver;
use App\Observers\ContractObserver;
use App\Observers\ExhibitorOrderObserver;
use App\Observers\InvoiceObserver;
use App\Reports\AdHocReportRunner;
use App\Reports\Handlers\ArAgingReport;
use App\Reports\Handlers\BalanceSheetReport;
use App\Reports\Handlers\BookedLocationsReport;
use App\Reports\Handlers\BudgetVsActualReport;
use App\Reports\Handlers\CalendarOfEventsReport;
use App\Reports\Handlers\ClerkMonthlyArReport;
use App\Reports\Handlers\EventAttendanceReport;
use App\Reports\Handlers\EventBulletinReport;
use App\Reports\Handlers\EventScheduleReport;
use App\Reports\Handlers\EventServicesScheduleReport;
use App\Reports\Handlers\EventStatusChangeReport;
use App\Reports\Handlers\FoodAndBeverageRequirementsReport;
use App\Reports\Handlers\ForecastVsActualReport;
use App\Reports\Handlers\IncomeStatementReport;
use App\Reports\Handlers\InventoryUtilizationReport;
use App\Reports\Handlers\LocationAvailabilityReport;
use App\Reports\Handlers\SalesGoalAttainmentReport;
use App\Reports\Handlers\SalesPipelineReport;
use App\Reports\Handlers\UserDefinedReportHandler;
use App\Reports\Handlers\WorkOrderStatusReport;
use App\Reports\ReportRegistry;
use App\Services\Accounting\FakeLedgerExporter;
use App\Services\Accounting\LedgerExporter;
use App\Services\Import\ImportRegistry;
use App\Services\Payments\FakeBluePayProcessor;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\SafeModePaymentProcessor;
use App\Services\Payments\StripeProcessor;
use App\Services\Signing\DocuSignSignatureProvider;
use App\Services\Signing\FakeSignatureProvider;
use App\Services\Signing\SignatureProvider;
use App\Services\SystemSettings\ConfigOverlay;
use App\Services\SystemSettings\SystemSettings;
use App\Services\SystemSettings\SystemSettingsRegistry;
use App\Support\PdfDriverGuard;
use App\Support\SafeMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fake signature provider by default; the real DocuSign driver swaps
        // in when integrations.docusign.enabled is on. lazy so seeding doesn't
        // touch SystemSettings before the table exists.
        $this->app->singleton(SignatureProvider::class, function ($app) {
            // safe mode forces the Fake driver so a non-prod instance can't
            // send a real e-signature envelope
            if (SafeMode::enabled()) {
                return new FakeSignatureProvider;
            }

            $settings = $app->make(SystemSettings::class);
            $enabled = (bool) $settings->get('integrations.docusign.enabled', false);

            return $enabled
                ? new DocuSignSignatureProvider($settings)
                : new FakeSignatureProvider;
        });

        // stub payment processor; the real payment driver swaps into the inner
        // slot once credentials + hosted-iframe tokenization are wired. the
        // SafeModePaymentProcessor decorator never delegates in safe mode, so a
        // non-prod instance can't charge a real account whatever driver is bound.
        $this->app->singleton(
            PaymentProcessor::class,
            fn ($app) => new SafeModePaymentProcessor($this->paymentDriver($app)),
        );

        // Stub ledger exporter. The real driver produces the
        // customer's specific format and uploads it to their GL.
        $this->app->singleton(LedgerExporter::class, FakeLedgerExporter::class);

        // report registry: singleton so handlers stay registered across the request
        $this->app->singleton(ReportRegistry::class);

        // import registry: singleton built from the configured importer classes
        $this->app->singleton(
            ImportRegistry::class,
            fn () => new ImportRegistry(config('import.importers', [])),
        );

        // system settings: registry holds the catalog, the service the
        // read/write API + cache; both singletons
        $this->app->singleton(SystemSettingsRegistry::class);
        $this->app->singleton(SystemSettings::class);

        // dashboard tile registry: singleton so registrations accumulate
        $this->app->singleton(DashboardTileRegistry::class);
    }

    /**
     * Resolve the configured payment gateway adapter (the inner driver wrapped
     * by SafeModePaymentProcessor). Selected by `payments.processor`; adding a
     * gateway is a config + adapter change.
     */
    private function paymentDriver(Application $app): PaymentProcessor
    {
        return match (config('payments.processor')) {
            'stripe' => new StripeProcessor(
                (string) config('payments.stripe.secret'),
                (string) config('payments.stripe.base_url'),
                (string) config('payments.stripe.currency'),
            ),
            default => $app->make(FakeBluePayProcessor::class),
        };
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->applySystemSettingsOverlay();

        // fail loud at boot if a deployed env is set to a PDF driver the image
        // can't run (only Gotenberg ships) rather than 500-ing every PDF route
        PdfDriverGuard::enforce($this->app->environment(), config('laravel-pdf.driver'));

        // safe mode: neutralize outbound email (drivers are Faked in register())
        SafeMode::applyMail();

        // opt-in strict models: surface N+1s as lazy-loading violations (never
        // in production). off by default; VELSA_STRICT_MODELS=1 to hunt them
        Model::preventLazyLoading(! $this->app->isProduction() && (bool) config('velsa.strict_models', false));

        // standard columns for user-definable lookup taxonomies; see
        // App\Models\Concerns\IsTaxonomy
        Blueprint::macro('taxonomyColumns', function (bool $withColor = false): void {
            /** @var Blueprint $this */
            $this->string('key')->unique();
            $this->string('label');
            if ($withColor) {
                $this->string('color')->default('slate');
            }
            $this->unsignedInteger('sort_order')->default(0);
            $this->boolean('is_active')->default(true);
            $this->boolean('is_system')->default(false);
            $this->timestamps();
        });

        // AuthAuditSubscriber's handle*() methods are auto-discovered (event
        // discovery is on); registering it via Event::subscribe() too would
        // double-fire and double-record. Same for PostPaymentJournalEntries.

        // canned named reports; user-defined ad-hoc reports come through the
        // ReportDefinition catalog at runtime
        $this->registerReports();

        // auto-narrative observers: append synthesized "System" narrative
        // entries on meaningful booking lifecycle events
        Booking::observe(BookingObserver::class);
        Contract::observe(ContractObserver::class);
        Invoice::observe(InvoiceObserver::class);
        ExhibitorOrder::observe(ExhibitorOrderObserver::class);

        // dashboard tile catalog; users pick + reorder via Dashboard -> Customize
        $this->registerDashboardTiles();
    }

    protected function registerDashboardTiles(): void
    {
        $registry = $this->app->make(DashboardTileRegistry::class);
        $registry->register($this->app->make(KpiStripTile::class));
        $registry->register($this->app->make(NeedsAttentionTile::class));
        $registry->register($this->app->make(RevenueTrendTile::class));
        $registry->register($this->app->make(PipelineByStageTile::class));
        $registry->register($this->app->make(BookingsByStatusTile::class));
        $registry->register($this->app->make(TodayOutlineTile::class));
        $registry->register($this->app->make(RecentActivityTile::class));
        $registry->register($this->app->make(MyOpenLeadsTile::class));
        $registry->register($this->app->make(MyUpcomingBookingsTile::class));
        $registry->register($this->app->make(PastDueInvoicesTile::class));
        $registry->register($this->app->make(QuickLinksTile::class));
    }

    protected function registerReports(): void
    {
        $registry = $this->app->make(ReportRegistry::class);
        $registry->register($this->app->make(BookedLocationsReport::class));
        $registry->register($this->app->make(LocationAvailabilityReport::class));
        $registry->register($this->app->make(EventBulletinReport::class));
        $registry->register($this->app->make(EventScheduleReport::class));
        $registry->register($this->app->make(CalendarOfEventsReport::class));
        $registry->register($this->app->make(EventAttendanceReport::class));
        $registry->register($this->app->make(EventServicesScheduleReport::class));
        $registry->register($this->app->make(SalesPipelineReport::class));
        $registry->register($this->app->make(SalesGoalAttainmentReport::class));
        $registry->register($this->app->make(ArAgingReport::class));
        $registry->register($this->app->make(ClerkMonthlyArReport::class));
        $registry->register($this->app->make(WorkOrderStatusReport::class));
        $registry->register($this->app->make(InventoryUtilizationReport::class));
        $registry->register($this->app->make(FoodAndBeverageRequirementsReport::class));
        $registry->register($this->app->make(EventStatusChangeReport::class));
        $registry->register($this->app->make(BudgetVsActualReport::class));
        $registry->register($this->app->make(BalanceSheetReport::class));
        $registry->register($this->app->make(IncomeStatementReport::class));
        $registry->register($this->app->make(ForecastVsActualReport::class));

        // user-defined reports from the report_definitions table; each becomes
        // a ReportHandler in /reports. guarded so it's skipped pre-migration
        $this->registerUserDefinedReports($registry);
    }

    protected function registerUserDefinedReports(ReportRegistry $registry): void
    {
        try {
            $defs = ReportDefinition::query()->get();
        } catch (\Throwable) {
            return; // table not migrated yet
        }
        $runner = $this->app->make(AdHocReportRunner::class);
        foreach ($defs as $def) {
            $registry->register(new UserDefinedReportHandler($def, $runner));
        }
    }

    /**
     * Overlay DB-stored system settings onto runtime config so existing
     * consumers reading config('sso.providers...') get the admin-UI value.
     * Wrapped in a try so a pre-migration table doesn't crash boot.
     */
    protected function applySystemSettingsOverlay(): void
    {
        try {
            $this->app->make(ConfigOverlay::class)->apply($this->app->make('config'));
        } catch (\Throwable) {
            // swallow: install-time or pre-migration
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // mint one CSP nonce per request and stamp it on every Vite tag; the
        // SecurityHeaders middleware emits the matching script-src nonce so the
        // CSP can block injected scripts. eager, so non-Vite responses get one too
        Vite::useCspNonce();

        // force https outside local: trustProxies handles request-scoped URLs,
        // but URLs generated off-request (queued mailers, CLI signed links) fall
        // back to APP_URL, so force the scheme to stay mixed-content-safe
        if (! app()->isLocal()) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(15) // STIG APSC-DV-001680 / NIST IA-5(1): 15-char minimum
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
