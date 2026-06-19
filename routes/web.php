<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuditRuleController;
use App\Http\Controllers\Admin\BrandingImageController;
use App\Http\Controllers\Admin\ChartOfAccountController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DocumentTemplateController;
use App\Http\Controllers\Admin\EquipmentItemController;
use App\Http\Controllers\Admin\EventKindController;
use App\Http\Controllers\Admin\ExhibitorPermitController;
use App\Http\Controllers\Admin\ExportTemplateController;
use App\Http\Controllers\Admin\FiscalYearController;
use App\Http\Controllers\Admin\FundController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\InsuranceCertificateController;
use App\Http\Controllers\Admin\InventoryKindController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\OutlineItemTemplateController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PipelineStageController;
use App\Http\Controllers\Admin\RateCardController;
use App\Http\Controllers\Admin\RatePackageController;
use App\Http\Controllers\Admin\ReportBuilderController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SalesGoalController;
use App\Http\Controllers\Admin\SpaceKindController;
use App\Http\Controllers\Admin\SsoMappingController;
use App\Http\Controllers\Admin\SupportRequestController as AdminSupportRequestController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\WorkOrderTemplateController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BookingStaffController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagramController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\ExhibitorController;
use App\Http\Controllers\ExhibitorEventController;
use App\Http\Controllers\IdentityImageController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LayoutTemplateController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LicensesController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OpsBoardController;
use App\Http\Controllers\OpsCalendarController;
use App\Http\Controllers\OpsScheduleController;
use App\Http\Controllers\OutlineController;
use App\Http\Controllers\PaymentScheduleController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\CatalogController as PortalCatalogController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\HandbookController as PortalHandbookController;
use App\Http\Controllers\Portal\InsuranceController as PortalInsuranceController;
use App\Http\Controllers\Portal\OrderController as PortalOrderController;
use App\Http\Controllers\Portal\PermitController as PortalPermitController;
use App\Http\Controllers\RecordDocumentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpaceConstraintController;
use App\Http\Controllers\SpaceController;
use App\Http\Controllers\SpaceFinderController;
use App\Http\Controllers\SupportRequestController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\Webhooks\DocuSignController;
use App\Http\Controllers\WhatsNewController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureGlobalPermission;
use App\Http\Middleware\EnsurePasswordCurrent;
use App\Http\Middleware\EnsurePrivilegedMfa;
use App\Http\Middleware\ScopePortalOrderOwnership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// third-party license attribution; required by preserve-notice clauses
Route::get('licenses', [LicensesController::class, 'show'])->name('licenses');

// deterministic identity image; public + no auth, seed carries no entity data
Route::get('identity/{seed}.svg', IdentityImageController::class)
    ->where('seed', '[A-Za-z0-9_-]+')
    ->name('identity.image');

// document download gateway; outside auth group so both web and exhibitor
// guards reach it, controller authorizes and fails closed (403)
Route::get('documents/{media}', [MediaController::class, 'show'])->name('media.show');

// webhooks; CSRF bypassed for webhooks/* in bootstrap/app.php (server-to-server)
Route::post('webhooks/docusign', [DocuSignController::class, 'handle'])
    ->name('webhooks.docusign');

// SSO sign-in; unauthenticated, provider constrained so unknown drivers 404 early
Route::get('auth/sso/{provider}', [SsoController::class, 'redirect'])
    ->where('provider', '[a-z][a-z0-9_-]*')
    ->name('sso.redirect');
Route::get('auth/sso/{provider}/callback', [SsoController::class, 'callback'])
    ->where('provider', '[a-z][a-z0-9_-]*')
    ->name('sso.callback');

Route::middleware(['auth', 'verified', EnsurePasswordCurrent::class])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::put('dashboard/preferences', [DashboardController::class, 'updatePreferences'])->name('dashboard.preferences');
    Route::put('dashboard/quick-links', [DashboardController::class, 'updateQuickLinks'])->name('dashboard.quick-links');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('spaces/find', [SpaceFinderController::class, 'index'])->name('spaces.find');
    Route::get('venues', [VenueController::class, 'index'])->name('venues.index');
    // static paths before the {venue} wildcard
    Route::get('venues/archive', [VenueController::class, 'archive'])->name('venues.archive');
    Route::get('venues/create', [VenueController::class, 'create'])->name('venues.create');
    Route::post('venues', [VenueController::class, 'store'])->name('venues.store');
    Route::get('venues/{venue}', [VenueController::class, 'show'])->name('venues.show');
    Route::get('venues/{venue}/edit', [VenueController::class, 'edit'])->name('venues.edit');
    Route::put('venues/{venue}', [VenueController::class, 'update'])->name('venues.update');
    Route::delete('venues/{venue}', [VenueController::class, 'destroy'])->name('venues.destroy');
    Route::patch('venues/{venue}/restore', [VenueController::class, 'restore'])->name('venues.restore')->withTrashed();
    Route::post('venues/{venue}/blackouts', [VenueController::class, 'storeBlackout'])->name('venues.blackouts.store');
    Route::delete('venues/{venue}/blackouts/{blackout}', [VenueController::class, 'destroyBlackout'])->name('venues.blackouts.destroy');

    Route::get('venues/{venue}/spaces/create', [SpaceController::class, 'create'])->name('spaces.create');
    Route::post('venues/{venue}/spaces', [SpaceController::class, 'store'])->name('spaces.store');
    Route::get('spaces/{space}/edit', [SpaceController::class, 'edit'])->name('spaces.edit');
    Route::get('spaces/{space}', [SpaceController::class, 'show'])->name('spaces.show');
    Route::put('spaces/{space}', [SpaceController::class, 'update'])->name('spaces.update');
    Route::delete('spaces/{space}', [SpaceController::class, 'destroy'])->name('spaces.destroy');
    Route::post('spaces/{space}/floorplan', [SpaceController::class, 'uploadFloorPlan'])->name('spaces.floorplan.store');
    Route::delete('spaces/{space}/floorplan', [SpaceController::class, 'deleteFloorPlan'])->name('spaces.floorplan.destroy');
    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    // static paths before the {client} wildcard
    Route::get('clients/archive', [ClientController::class, 'archive'])->name('clients.archive');
    Route::get('clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::get('clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');
    Route::patch('clients/{client}/restore', [ClientController::class, 'restore'])->name('clients.restore')->withTrashed();
    Route::post('clients/{client}/documents', [RecordDocumentController::class, 'storeClient'])
        ->middleware(EnsureGlobalPermission::class.':clients.manage')->name('clients.documents.store');
    Route::delete('clients/{client}/documents/{media}', [RecordDocumentController::class, 'destroyClient'])
        ->middleware(EnsureGlobalPermission::class.':clients.manage')->name('clients.documents.destroy');
    Route::post('clients/{client}/contacts', [ClientController::class, 'storeContact'])->name('clients.contacts.store');
    Route::put('clients/{client}/contacts/{contact}', [ClientController::class, 'updateContact'])->name('clients.contacts.update')->scopeBindings();
    Route::delete('clients/{client}/contacts/{contact}', [ClientController::class, 'destroyContact'])->name('clients.contacts.destroy')->scopeBindings();
    Route::get('pipeline', [PipelineController::class, 'index'])->name('pipeline.index');
    Route::get('pipeline/archive', [PipelineController::class, 'archive'])->name('pipeline.archive');
    // static create must precede the {lead} wildcard below
    Route::get('leads/create', [LeadController::class, 'create'])->name('leads.create');
    Route::post('leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('leads/{lead}', [LeadController::class, 'show'])->name('leads.show');
    Route::get('leads/{lead}/edit', [LeadController::class, 'edit'])->name('leads.edit');
    Route::put('leads/{lead}', [LeadController::class, 'update'])->name('leads.update');
    Route::patch('leads/{lead}/stage', [LeadController::class, 'updateStage'])->name('leads.stage');
    Route::patch('leads/{lead}/reopen', [LeadController::class, 'reopen'])->name('leads.reopen');
    Route::patch('leads/{lead}/archive', [LeadController::class, 'archive'])->name('leads.archive');
    Route::post('leads/{lead}/clone', [LeadController::class, 'clone'])->name('leads.clone');
    Route::post('leads/{lead}/activities', [LeadController::class, 'storeActivity'])->name('leads.activities.store');
    Route::patch('leads/{lead}/activities/{activity}/toggle', [LeadController::class, 'toggleActivity'])->name('leads.activities.toggle');
    Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/create', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::get('bookings/{booking}/settlement.pdf', [BookingController::class, 'downloadSettlement'])->name('bookings.settlement');
    Route::get('bookings/{booking}/edit', [BookingController::class, 'edit'])->name('bookings.edit');
    Route::put('bookings/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::post('bookings/{booking}/clone', [BookingController::class, 'clone'])->name('bookings.clone');
    Route::post('bookings/{booking}/narratives', [BookingController::class, 'storeNarrative'])->name('bookings.narratives.store');
    Route::post('bookings/{booking}/documents', [RecordDocumentController::class, 'storeBooking'])
        ->middleware(EnsureGlobalPermission::class.':bookings.edit')->name('bookings.documents.store');
    Route::delete('bookings/{booking}/documents/{media}', [RecordDocumentController::class, 'destroyBooking'])
        ->middleware(EnsureGlobalPermission::class.':bookings.edit')->name('bookings.documents.destroy');
    Route::post('bookings/{booking}/staff', [BookingStaffController::class, 'store'])->name('bookings.staff.store');
    Route::delete('staff-assignments/{assignment}', [BookingStaffController::class, 'destroy'])->name('bookings.staff.destroy');
    Route::put('bookings/{booking}/payment-schedule', [PaymentScheduleController::class, 'replace'])->name('bookings.payment-schedule.replace');
    Route::delete('payment-schedules/{paymentSchedule}', [PaymentScheduleController::class, 'destroy'])->name('payment-schedules.destroy');
    Route::get('bookings/{booking}/diagram', [DiagramController::class, 'show'])->name('bookings.diagram');
    Route::post('bookings/{booking}/diagram', [DiagramController::class, 'store'])->name('bookings.diagram.store');
    Route::post('diagrams/{diagram}/apply-template/{layoutTemplate}', [LayoutTemplateController::class, 'apply'])->name('diagrams.apply-template');
    Route::post('diagrams/{diagram}/save-as-template', [LayoutTemplateController::class, 'saveAs'])->name('diagrams.save-as-template');
    Route::post('bookings/{booking}/contracts', [ContractController::class, 'draftFromBooking'])->name('bookings.contracts.draft');
    Route::get('bookings/{booking}/outline', [OutlineController::class, 'show'])->name('bookings.outline');
    Route::post('bookings/{booking}/outline/items', [OutlineController::class, 'storeItem'])->name('bookings.outline.items.store');
    Route::post('bookings/{booking}/outline/publish', [OutlineController::class, 'publish'])->name('bookings.outline.publish');
    Route::get('bookings/{booking}/outline.pdf', [OutlineController::class, 'downloadPdf'])->name('bookings.outline.pdf');
    Route::patch('outline-items/{item}', [OutlineController::class, 'updateItem'])->name('outline-items.update');
    Route::delete('outline-items/{item}', [OutlineController::class, 'destroyItem'])->name('outline-items.destroy');
    Route::post('outline-items/{item}/tasks', [OutlineController::class, 'storeTask'])->name('outline-items.tasks.store');
    Route::patch('outline-item-tasks/{task}/toggle', [OutlineController::class, 'toggleTask'])->name('outline-item-tasks.toggle');
    Route::delete('outline-item-tasks/{task}', [OutlineController::class, 'destroyTask'])->name('outline-item-tasks.destroy');
    Route::get('ops/board', [OpsBoardController::class, 'index'])->name('ops.board');
    Route::get('ops/schedule', [OpsScheduleController::class, 'index'])->name('ops.schedule');
    Route::get('ops/calendar', [OpsCalendarController::class, 'index'])->name('ops.calendar');
    Route::get('contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('contracts/archive', [ContractController::class, 'archive'])->name('contracts.archive');
    Route::get('contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('contracts/{contract}/signed-pdf', [ContractController::class, 'downloadSigned'])->name('contracts.signed-pdf');
    Route::get('contracts/{contract}/document.doc', [ContractController::class, 'downloadWord'])->name('contracts.word');
    Route::post('contracts/{contract}/send', [ContractController::class, 'send'])->name('contracts.send');
    Route::post('contracts/{contract}/void', [ContractController::class, 'void'])->name('contracts.void');
    Route::post('contracts/{contract}/addenda', [ContractController::class, 'draftAddendum'])->name('contracts.addenda.draft');
    Route::delete('contracts/{contract}', [ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::patch('contracts/{contract}/restore', [ContractController::class, 'restore'])->name('contracts.restore')->withTrashed();
    Route::get('exhibitors', [ExhibitorController::class, 'index'])->name('exhibitors.index');
    Route::post('exhibitors', [ExhibitorController::class, 'store'])->name('exhibitors.store');
    Route::post('exhibitor-events', [ExhibitorEventController::class, 'store'])->name('exhibitor-events.store');
    Route::patch('exhibitor-events/{event}', [ExhibitorEventController::class, 'update'])->name('exhibitor-events.update');
    Route::delete('exhibitor-events/{event}', [ExhibitorEventController::class, 'destroy'])->name('exhibitor-events.destroy');
    Route::get('exhibitor-events/{event}', [ExhibitorController::class, 'showEvent'])->name('exhibitor-events.show');
    Route::get('exhibitors/{exhibitor}', [ExhibitorController::class, 'show'])->name('exhibitors.show');
    Route::patch('exhibitors/{exhibitor}', [ExhibitorController::class, 'update'])->name('exhibitors.update');
    Route::delete('exhibitors/{exhibitor}', [ExhibitorController::class, 'destroy'])->name('exhibitors.destroy');
    // scoped bindings 404 on cross-owner access so controllers skip ownership
    // checks (STIG / NIST AC-3, IDOR consolidation)
    Route::scopeBindings()->group(function (): void {
        Route::get('exhibitors/{exhibitor}/orders/{order}', [ExhibitorController::class, 'showOrder'])->name('exhibitors.orders.show');
        Route::post('exhibitors/{exhibitor}/orders/{order}/items', [ExhibitorController::class, 'addOrderItem'])->name('exhibitors.orders.items.add');
        Route::patch('exhibitors/{exhibitor}/orders/{order}/items/{item}', [ExhibitorController::class, 'updateOrderItem'])->name('exhibitors.orders.items.update');
        Route::delete('exhibitors/{exhibitor}/orders/{order}/items/{item}', [ExhibitorController::class, 'removeOrderItem'])->name('exhibitors.orders.items.remove');
        Route::patch('exhibitors/{exhibitor}/orders/{order}/status', [ExhibitorController::class, 'setOrderStatus'])->name('exhibitors.orders.status');
        Route::post('exhibitors/{exhibitor}/orders/{order}/payments', [ExhibitorController::class, 'capturePayment'])->name('exhibitors.orders.payments.capture');
        Route::post('exhibitors/{exhibitor}/orders/{order}/payments/manual', [ExhibitorController::class, 'recordManualPayment'])->name('exhibitors.orders.payments.manual');
        Route::post('exhibitors/{exhibitor}/orders/{order}/payments/{payment}/refund', [ExhibitorController::class, 'refundPayment'])->name('exhibitors.orders.payments.refund');
    });
    Route::post('exhibitors/{exhibitor}/portal-link', [ExhibitorController::class, 'issuePortalLink'])->name('exhibitors.portal-link');
    Route::get('work-orders', [WorkOrderController::class, 'index'])->name('work-orders.index');
    Route::get('work-orders/create', [WorkOrderController::class, 'create'])->name('work-orders.create');
    Route::post('work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
    Route::get('work-orders/print', [WorkOrderController::class, 'printGroup'])->name('work-orders.print');
    Route::get('work-orders/{workOrder}', [WorkOrderController::class, 'show'])->name('work-orders.show');
    Route::get('work-orders/{workOrder}/print', [WorkOrderController::class, 'printOne'])->name('work-orders.print-one');
    Route::patch('work-orders/{workOrder}', [WorkOrderController::class, 'update'])->name('work-orders.update');
    Route::patch('work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus'])->name('work-orders.status');
    Route::delete('work-orders/{workOrder}', [WorkOrderController::class, 'destroy'])->name('work-orders.destroy');
    Route::post('work-orders/{workOrder}/items', [WorkOrderController::class, 'storeItem'])->name('work-orders.items.store');
    Route::patch('work-order-items/{workOrderItem}', [WorkOrderController::class, 'updateItem'])->name('work-order-items.update');
    Route::delete('work-order-items/{workOrderItem}', [WorkOrderController::class, 'destroyItem'])->name('work-order-items.destroy');
    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/activity', [InventoryController::class, 'activity'])->name('inventory.activity');
    Route::get('inventory/print', [InventoryController::class, 'printSheet'])->name('inventory.print');
    Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
    Route::patch('inventory/{resourceInventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('inventory/{resourceInventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('accounting', [AccountingController::class, 'journal'])
        ->middleware(EnsureGlobalPermission::class.':accounting.view')
        ->name('accounting.journal');
    Route::get('accounting/trial-balance', [AccountingController::class, 'trialBalance'])
        ->middleware(EnsureGlobalPermission::class.':accounting.view')
        ->name('accounting.trial-balance');
    Route::get('accounting/accounts/{chartOfAccount}', [AccountingController::class, 'accountLedger'])
        ->middleware(EnsureGlobalPermission::class.':accounting.view')
        ->name('accounting.account-ledger');
    Route::post('accounting/journal', [AccountingController::class, 'storeEntry'])
        ->middleware(EnsureGlobalPermission::class.':accounting.post_journal')
        ->name('accounting.journal.store');
    Route::post('accounting/journal/{journalEntry}/reverse', [AccountingController::class, 'reverseEntry'])
        ->middleware(EnsureGlobalPermission::class.':accounting.post_journal')
        ->name('accounting.journal.reverse');
    Route::post('accounting/export', [AccountingController::class, 'export'])
        ->middleware(EnsureGlobalPermission::class.':accounting.export_ledger')
        ->name('accounting.export');
    Route::get('accounting/batches/{batch}/download.csv', [AccountingController::class, 'downloadBatch'])
        ->middleware(EnsureGlobalPermission::class.':accounting.export_ledger')
        ->name('accounting.batch.download');
    Route::get('accounting/batches/{batch}', [AccountingController::class, 'showBatch'])
        ->middleware(EnsureGlobalPermission::class.':accounting.view')
        ->name('accounting.batch.show');
    Route::post('accounting/batches/{batch}/void', [AccountingController::class, 'voidBatch'])
        ->middleware(EnsureGlobalPermission::class.':accounting.export_ledger')
        ->name('accounting.batch.void');
    Route::post('accounting/batches/{batch}/acknowledge', [AccountingController::class, 'acknowledgeBatch'])
        ->middleware(EnsureGlobalPermission::class.':accounting.export_ledger')
        ->name('accounting.batch.acknowledge');
    Route::post('accounting/batches/{batch}/resend', [AccountingController::class, 'resendBatch'])
        ->middleware(EnsureGlobalPermission::class.':accounting.export_ledger')
        ->name('accounting.batch.resend');
    Route::middleware(EnsureGlobalPermission::class.':reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{slug}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{slug}/export.csv', [ReportController::class, 'exportCsv'])->name('reports.export');
        Route::get('reports/{slug}/export.pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('reports/{slug}/export.xlsx', [ReportController::class, 'exportXlsx'])->name('reports.export.xlsx');
    });
    Route::post('reports/{slug}/schedules', [ReportController::class, 'storeSchedule'])->name('reports.schedules.store');
    Route::delete('reports/{slug}/schedules/{schedule}', [ReportController::class, 'destroySchedule'])->name('reports.schedules.destroy');

    Route::get('docs', [DocsController::class, 'index'])->name('docs.index');
    Route::get('docs/{slug}', [DocsController::class, 'show'])->where('slug', '.*')->name('docs.show');

    Route::get('whats-new', [WhatsNewController::class, 'index'])->name('whats-new.index');

    Route::get('support', [SupportRequestController::class, 'create'])->name('support.create');
    Route::post('support', [SupportRequestController::class, 'store'])->name('support.store');

    Route::prefix('admin')->name('admin.')->middleware([EnsurePrivilegedMfa::class, EnsureAdminPermission::class])->group(function () {
        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/disable', [AdminUserController::class, 'disable'])->name('users.disable');
        Route::post('users/{user}/enable', [AdminUserController::class, 'enable'])->name('users.enable');
        Route::post('users/{user}/require-password-change', [AdminUserController::class, 'requirePasswordChange'])->name('users.require-password-change');
        Route::post('users/{user}/assignments', [AdminUserController::class, 'assignRole'])->name('users.assignments.store');
        Route::delete('users/{user}/assignments', [AdminUserController::class, 'unassignRole'])->name('users.assignments.destroy');

        Route::get('roles', [AdminRoleController::class, 'index'])->name('roles.index');
        Route::get('roles/create', [AdminRoleController::class, 'create'])->name('roles.create');
        Route::post('roles', [AdminRoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/clone', [AdminRoleController::class, 'clone'])->name('roles.clone');
        Route::get('roles/{role}', [AdminRoleController::class, 'show'])->name('roles.show');
        Route::put('roles/{role}', [AdminRoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [AdminRoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('permissions', [AdminPermissionController::class, 'index'])->name('permissions.index');
        Route::post('permissions', [AdminPermissionController::class, 'store'])->name('permissions.store');
        Route::delete('permissions/{permission}', [AdminPermissionController::class, 'destroy'])->name('permissions.destroy');
        Route::get('permissions/{name}', [AdminPermissionController::class, 'show'])
            ->where('name', '[a-z_.]+')
            ->name('permissions.show');
        Route::get('users/{user}/permissions', [AdminPermissionController::class, 'userMatrix'])
            ->name('users.permissions');

        Route::get('sso-mappings', [SsoMappingController::class, 'index'])->name('sso-mappings.index');
        Route::get('sso-mappings/create', [SsoMappingController::class, 'create'])->name('sso-mappings.create');
        Route::post('sso-mappings', [SsoMappingController::class, 'store'])->name('sso-mappings.store');
        Route::get('sso-mappings/{mapping}', [SsoMappingController::class, 'show'])->name('sso-mappings.show');
        Route::put('sso-mappings/{mapping}', [SsoMappingController::class, 'update'])->name('sso-mappings.update');
        Route::delete('sso-mappings/{mapping}', [SsoMappingController::class, 'destroy'])->name('sso-mappings.destroy');
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('audit/export.csv', [AuditController::class, 'exportCsv'])->name('audit.export');
        Route::get('audit-rules', [AuditRuleController::class, 'index'])->name('audit-rules.index');
        Route::post('audit-rules', [AuditRuleController::class, 'store'])->name('audit-rules.store');
        Route::put('audit-rules/{auditRule}', [AuditRuleController::class, 'update'])->name('audit-rules.update');
        Route::delete('audit-rules/{auditRule}', [AuditRuleController::class, 'destroy'])->name('audit-rules.destroy');

        Route::get('support-requests', [AdminSupportRequestController::class, 'index'])->name('support-requests.index');
        Route::put('support-requests/{supportRequest}', [AdminSupportRequestController::class, 'update'])->name('support-requests.update');

        Route::get('insurance-certificates', [InsuranceCertificateController::class, 'index'])->name('insurance-certificates.index');
        Route::get('insurance-certificates/create', [InsuranceCertificateController::class, 'create'])->name('insurance-certificates.create');
        Route::post('insurance-certificates', [InsuranceCertificateController::class, 'store'])->name('insurance-certificates.store');
        Route::put('insurance-certificates/{insuranceCertificate}', [InsuranceCertificateController::class, 'update'])->name('insurance-certificates.update');
        Route::delete('insurance-certificates/{insuranceCertificate}', [InsuranceCertificateController::class, 'destroy'])->name('insurance-certificates.destroy');

        Route::get('exhibitor-permits', [ExhibitorPermitController::class, 'index'])->name('exhibitor-permits.index');
        Route::put('exhibitor-permits/{exhibitorPermit}', [ExhibitorPermitController::class, 'update'])->name('exhibitor-permits.update');

        Route::get('rate-cards', [RateCardController::class, 'index'])->name('rate-cards.index');
        Route::get('rate-cards/create', [RateCardController::class, 'create'])->name('rate-cards.create');
        Route::post('rate-cards', [RateCardController::class, 'store'])->name('rate-cards.store');
        Route::get('rate-cards/{rateCard}/edit', [RateCardController::class, 'edit'])->name('rate-cards.edit');
        Route::put('rate-cards/{rateCard}', [RateCardController::class, 'update'])->name('rate-cards.update');
        Route::delete('rate-cards/{rateCard}', [RateCardController::class, 'destroy'])->name('rate-cards.destroy');

        Route::get('rate-packages', [RatePackageController::class, 'index'])->name('rate-packages.index');
        Route::get('rate-packages/create', [RatePackageController::class, 'create'])->name('rate-packages.create');
        Route::post('rate-packages', [RatePackageController::class, 'store'])->name('rate-packages.store');
        Route::get('rate-packages/{ratePackage}/edit', [RatePackageController::class, 'edit'])->name('rate-packages.edit');
        Route::put('rate-packages/{ratePackage}', [RatePackageController::class, 'update'])->name('rate-packages.update');
        Route::delete('rate-packages/{ratePackage}', [RatePackageController::class, 'destroy'])->name('rate-packages.destroy');

        Route::get('layout-templates', [LayoutTemplateController::class, 'index'])->name('layout-templates.index');
        Route::delete('layout-templates/{layoutTemplate}', [LayoutTemplateController::class, 'destroy'])->name('layout-templates.destroy');

        Route::get('document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
        Route::get('document-templates/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
        Route::post('document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::get('document-templates/{documentTemplate}', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
        Route::put('document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
        Route::delete('document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy');

        Route::get('spaces/{space}/constraints', [SpaceConstraintController::class, 'show'])->name('spaces.constraints');
        Route::post('spaces/{space}/constraints', [SpaceConstraintController::class, 'store'])->name('spaces.constraints.store');

        Route::get('space-kinds', [SpaceKindController::class, 'index'])->name('space-kinds.index');
        Route::post('space-kinds', [SpaceKindController::class, 'store'])->name('space-kinds.store');
        Route::put('space-kinds/{spaceKind}', [SpaceKindController::class, 'update'])->name('space-kinds.update');
        Route::patch('space-kinds/{spaceKind}/toggle', [SpaceKindController::class, 'toggle'])->name('space-kinds.toggle');
        Route::patch('space-kinds/{spaceKind}/move', [SpaceKindController::class, 'move'])->name('space-kinds.move');
        Route::delete('space-kinds/{spaceKind}', [SpaceKindController::class, 'destroy'])->name('space-kinds.destroy');

        Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('departments/{department}/toggle', [DepartmentController::class, 'toggle'])->name('departments.toggle');
        Route::patch('departments/{department}/move', [DepartmentController::class, 'move'])->name('departments.move');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

        Route::get('event-kinds', [EventKindController::class, 'index'])->name('event-kinds.index');
        Route::post('event-kinds', [EventKindController::class, 'store'])->name('event-kinds.store');
        Route::put('event-kinds/{eventKind}', [EventKindController::class, 'update'])->name('event-kinds.update');
        Route::patch('event-kinds/{eventKind}/toggle', [EventKindController::class, 'toggle'])->name('event-kinds.toggle');
        Route::patch('event-kinds/{eventKind}/move', [EventKindController::class, 'move'])->name('event-kinds.move');
        Route::delete('event-kinds/{eventKind}', [EventKindController::class, 'destroy'])->name('event-kinds.destroy');

        Route::get('equipment-items', [EquipmentItemController::class, 'index'])->name('equipment-items.index');
        Route::post('equipment-items', [EquipmentItemController::class, 'store'])->name('equipment-items.store');
        Route::put('equipment-items/{equipmentItem}', [EquipmentItemController::class, 'update'])->name('equipment-items.update');
        Route::patch('equipment-items/{equipmentItem}/toggle', [EquipmentItemController::class, 'toggle'])->name('equipment-items.toggle');

        Route::get('inventory-kinds', [InventoryKindController::class, 'index'])->name('inventory-kinds.index');
        Route::post('inventory-kinds', [InventoryKindController::class, 'store'])->name('inventory-kinds.store');
        Route::put('inventory-kinds/{inventoryKind}', [InventoryKindController::class, 'update'])->name('inventory-kinds.update');
        Route::patch('inventory-kinds/{inventoryKind}/toggle', [InventoryKindController::class, 'toggle'])->name('inventory-kinds.toggle');
        Route::patch('inventory-kinds/{inventoryKind}/move', [InventoryKindController::class, 'move'])->name('inventory-kinds.move');
        Route::delete('inventory-kinds/{inventoryKind}', [InventoryKindController::class, 'destroy'])->name('inventory-kinds.destroy');

        Route::get('outline-item-templates', [OutlineItemTemplateController::class, 'index'])->name('outline-item-templates.index');
        Route::post('outline-item-templates', [OutlineItemTemplateController::class, 'store'])->name('outline-item-templates.store');
        Route::put('outline-item-templates/{outlineItemTemplate}', [OutlineItemTemplateController::class, 'update'])->name('outline-item-templates.update');
        Route::patch('outline-item-templates/{outlineItemTemplate}/toggle', [OutlineItemTemplateController::class, 'toggle'])->name('outline-item-templates.toggle');
        Route::delete('outline-item-templates/{outlineItemTemplate}', [OutlineItemTemplateController::class, 'destroy'])->name('outline-item-templates.destroy');

        Route::get('work-order-templates', [WorkOrderTemplateController::class, 'index'])->name('work-order-templates.index');
        Route::post('work-order-templates', [WorkOrderTemplateController::class, 'store'])->name('work-order-templates.store');
        Route::put('work-order-templates/{workOrderTemplate}', [WorkOrderTemplateController::class, 'update'])->name('work-order-templates.update');
        Route::delete('work-order-templates/{workOrderTemplate}', [WorkOrderTemplateController::class, 'destroy'])->name('work-order-templates.destroy');

        Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('imports', [ImportController::class, 'store'])->name('imports.store');
        Route::get('imports/{importJob}', [ImportController::class, 'show'])->name('imports.show');
        Route::get('imports/{importJob}/errors', [ImportController::class, 'errors'])->name('imports.errors');
        Route::post('imports/{importJob}/preview', [ImportController::class, 'preview'])->name('imports.preview');
        Route::post('imports/{importJob}/commit', [ImportController::class, 'commit'])->name('imports.commit');
        Route::post('imports/{importJob}/reverse', [ImportController::class, 'reverse'])->name('imports.reverse');
        Route::delete('imports/{importJob}', [ImportController::class, 'destroy'])->name('imports.destroy');

        Route::get('chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::post('chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
        Route::put('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
        Route::delete('chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');
        Route::get('funds', [FundController::class, 'index'])->name('funds.index');
        Route::post('funds', [FundController::class, 'store'])->name('funds.store');
        Route::put('funds/{fund}', [FundController::class, 'update'])->name('funds.update');
        Route::delete('funds/{fund}', [FundController::class, 'destroy'])->name('funds.destroy');

        Route::get('fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
        Route::post('fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');
        Route::get('fiscal-years/{year}', [FiscalYearController::class, 'show'])->name('fiscal-years.show');
        Route::post('fiscal-years/{year}/budgets', [FiscalYearController::class, 'storeBudget'])->name('fiscal-years.budgets.store');
        Route::delete('fiscal-years/{year}/budgets/{budget}', [FiscalYearController::class, 'destroyBudget'])->name('fiscal-years.budgets.destroy');
        Route::post('fiscal-years/{year}/close', [FiscalYearController::class, 'close'])->name('fiscal-years.close');
        Route::post('fiscal-years/{year}/reopen', [FiscalYearController::class, 'reopen'])->name('fiscal-years.reopen');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::patch('invoices/{invoice}/references', [InvoiceController::class, 'updateReferences'])->name('invoices.references');
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.record');
        Route::post('invoices/{invoice}/payments/{payment}/refund', [InvoiceController::class, 'refund'])->name('invoices.payments.refund');
        Route::post('invoices/{invoice}/refund', [InvoiceController::class, 'refundInvoice'])->name('invoices.refund');
        Route::post('invoices/{invoice}/write-off', [InvoiceController::class, 'writeOff'])->name('invoices.write-off');
        Route::get('exhibitors/{exhibitor}/statement', [InvoiceController::class, 'statement'])->name('exhibitors.statement');
        Route::post('bookings/{booking}/invoices/deposit', [InvoiceController::class, 'issueBookingDeposit'])->name('bookings.invoices.deposit');
        Route::post('bookings/{booking}/invoices/balance', [InvoiceController::class, 'issueBookingBalance'])->name('bookings.invoices.balance');

        Route::get('report-builder', [ReportBuilderController::class, 'index'])->name('report-builder.index');
        Route::get('report-builder/create', [ReportBuilderController::class, 'create'])->name('report-builder.create');
        Route::post('report-builder', [ReportBuilderController::class, 'store'])->name('report-builder.store');
        Route::get('report-builder/{definition}', [ReportBuilderController::class, 'show'])->name('report-builder.show');
        Route::get('report-builder/{definition}/edit', [ReportBuilderController::class, 'edit'])->name('report-builder.edit');
        Route::put('report-builder/{definition}', [ReportBuilderController::class, 'update'])->name('report-builder.update');
        Route::delete('report-builder/{definition}', [ReportBuilderController::class, 'destroy'])->name('report-builder.destroy');

        Route::get('export-templates', [ExportTemplateController::class, 'index'])->name('export-templates.index');
        Route::get('export-templates/create', [ExportTemplateController::class, 'create'])->name('export-templates.create');
        Route::post('export-templates', [ExportTemplateController::class, 'store'])->name('export-templates.store');
        Route::post('export-templates/preview', [ExportTemplateController::class, 'preview'])->name('export-templates.preview');
        Route::get('export-templates/{template}/edit', [ExportTemplateController::class, 'edit'])->name('export-templates.edit');
        Route::put('export-templates/{template}', [ExportTemplateController::class, 'update'])->name('export-templates.update');
        Route::post('export-templates/{template}/default', [ExportTemplateController::class, 'setDefault'])->name('export-templates.set-default');
        Route::delete('export-templates/{template}', [ExportTemplateController::class, 'destroy'])->name('export-templates.destroy');

        Route::get('system-settings', [SystemSettingController::class, 'index'])->name('system-settings.index');
        Route::put('system-settings', [SystemSettingController::class, 'update'])->name('system-settings.update');

        Route::get('branding-images', [BrandingImageController::class, 'index'])->name('branding-images.index');
        Route::post('branding-images', [BrandingImageController::class, 'store'])->name('branding-images.store');
        Route::put('branding-images/{brandingImage}', [BrandingImageController::class, 'update'])->name('branding-images.update');
        Route::delete('branding-images/{brandingImage}', [BrandingImageController::class, 'destroy'])->name('branding-images.destroy');

        Route::get('pipeline-stages', [PipelineStageController::class, 'index'])->name('pipeline-stages.index');
        Route::put('pipeline-stages', [PipelineStageController::class, 'update'])->name('pipeline-stages.update');

        Route::get('sales-goals', [SalesGoalController::class, 'index'])->name('sales-goals.index');
        Route::post('sales-goals', [SalesGoalController::class, 'store'])->name('sales-goals.store');
        Route::put('sales-goals/{salesGoal}', [SalesGoalController::class, 'update'])->name('sales-goals.update');
        Route::delete('sales-goals/{salesGoal}', [SalesGoalController::class, 'destroy'])->name('sales-goals.destroy');
    });
});

// exhibitor portal; exhibitor guard (magic-link only, no password). kept
// outside the staff-auth group so unauthenticated exhibitors reach login.

Route::prefix('portal')->name('portal.')->group(function () {
    // public - access request + magic-link landing + logout
    Route::get('access', [PortalAuthController::class, 'access'])->name('access');
    Route::post('access', [PortalAuthController::class, 'requestLink'])
        ->middleware('throttle:6,1')
        ->name('access.request');
    Route::get('login/{token}', [PortalAuthController::class, 'login'])
        ->where('token', '[A-Za-z0-9\-_]+')
        ->name('login');
    Route::post('logout', [PortalAuthController::class, 'logout'])->name('logout');

    // authenticated portal pages
    Route::middleware(['auth:exhibitor', ScopePortalOrderOwnership::class])->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('dashboard');
        Route::get('catalog', [PortalCatalogController::class, 'index'])->name('catalog');
        Route::get('orders/{order}', [PortalOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/items', [PortalOrderController::class, 'addItem'])->name('orders.items.add');
        Route::delete('orders/{order}/items/{item}', [PortalOrderController::class, 'removeItem'])->name('orders.items.remove')->scopeBindings();
        Route::get('orders/{order}/pay', [PortalOrderController::class, 'pay'])->name('orders.pay');
        Route::post('orders/{order}/pay', [PortalOrderController::class, 'processPayment'])->name('orders.process-payment');
        Route::get('orders/{order}/invoice', [PortalOrderController::class, 'invoice'])->name('orders.invoice');

        Route::get('insurance', [PortalInsuranceController::class, 'index'])->name('insurance.index');
        Route::post('insurance', [PortalInsuranceController::class, 'store'])->name('insurance.store');
        Route::get('handbook', [PortalHandbookController::class, 'show'])->name('handbook');
        Route::post('handbook/acknowledge', [PortalHandbookController::class, 'acknowledge'])->name('handbook.acknowledge');

        Route::get('permits', [PortalPermitController::class, 'index'])->name('permits.index');
        Route::post('permits', [PortalPermitController::class, 'store'])->name('permits.store');
        Route::post('permits/{permit}/cancel', [PortalPermitController::class, 'cancel'])->name('permits.cancel');
    });
});

require __DIR__.'/settings.php';
