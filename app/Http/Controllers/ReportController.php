<?php

namespace App\Http\Controllers;

use App\Models\ReportSchedule;
use App\Reports\ReportHandler;
use App\Reports\ReportRegistry;
use App\Services\AuditLogger;
use App\Services\Reports\ReportXlsxExporter;
use App\Support\Csv;
use App\Support\DateFormatter;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportRegistry $registry) {}

    public function index(): Response
    {
        $groups = collect($this->registry->grouped())
            ->map(fn ($handlers, $category) => [
                'category' => $category,
                'handlers' => collect($handlers)->map(fn (ReportHandler $h) => [
                    'slug' => $h->slug(),
                    'title' => $h->title(),
                    'description' => $h->description(),
                ])->values(),
            ])
            ->values();

        return Inertia::render('reports/index', [
            'groups' => $groups,
        ]);
    }

    public function show(string $slug, Request $request): Response
    {
        abort_unless($this->registry->has($slug), 404);

        $handler = $this->registry->get($slug);
        $params = $this->resolveParams($handler, $request);
        $result = $handler->run($params);

        return Inertia::render('reports/show', [
            'handler' => [
                'slug' => $handler->slug(),
                'title' => $handler->title(),
                'category' => $handler->category(),
                'description' => $handler->description(),
                'parameters' => $handler->parameters(),
            ],
            'params' => $params,
            'result' => [
                'title' => $result->title,
                'description' => $result->description,
                'columns' => $result->columns,
                'rows' => $result->rows,
                'summary' => $result->summary,
                'generated_at' => $result->generatedAt,
            ],
            'can_schedule' => (bool) $request->user()?->hasVenuePermission('reports.schedule'),
            'schedules' => ReportSchedule::query()
                ->where('report_slug', $slug)
                ->latest()
                ->get()
                ->map(fn (ReportSchedule $s) => [
                    'id' => $s->id,
                    'cadence' => $s->cadenceLabel(),
                    'format' => $s->format,
                    'recipients' => $s->recipients,
                    'is_active' => $s->is_active,
                    'last_run_at' => $s->last_run_at?->toDateTimeString(),
                ]),
        ]);
    }

    public function storeSchedule(string $slug, Request $request): RedirectResponse
    {
        abort_unless($this->registry->has($slug), 404);
        abort_unless((bool) $request->user()?->hasVenuePermission('reports.schedule'), 403);

        $handler = $this->registry->get($slug);

        $maxRecipients = (int) config('velsa.report_max_recipients', 20);

        $data = $request->validate([
            'format' => ['required', Rule::in(['csv', 'xlsx', 'pdf'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'day_of_week' => ['nullable', 'integer', 'between:0,6', 'required_if:frequency,weekly'],
            'day_of_month' => ['nullable', 'integer', 'between:1,28', 'required_if:frequency,monthly'],
            'hour' => ['required', 'integer', 'between:0,23'],
            'recipients' => ['required', 'array', 'min:1', "max:{$maxRecipients}"],
            'recipients.*' => ['email', $this->recipientDomainRule()],
        ]);

        // dedupe so the cap can't be sidestepped and nobody is mailed twice per run
        $recipients = array_values(array_unique($data['recipients']));

        $schedule = ReportSchedule::query()->create([
            'report_slug' => $slug,
            // snapshot current filter params so each run reproduces the same view
            'params_json' => $this->resolveParams($handler, $request),
            'format' => $data['format'],
            'frequency' => $data['frequency'],
            'day_of_week' => $data['frequency'] === 'weekly' ? $data['day_of_week'] : null,
            'day_of_month' => $data['frequency'] === 'monthly' ? $data['day_of_month'] : null,
            'hour' => $data['hour'],
            'recipients' => $recipients,
            'created_by_user_id' => $request->user()->id,
        ]);

        // recurring report-by-email is a standing data-egress channel; record who
        // opened it and to where (NIST AC-4 information flow / AU-12)
        app(AuditLogger::class)->record(
            eventType: 'report_schedule.created',
            subject: $schedule,
            payload: [
                'report_slug' => $slug,
                'recipients' => $recipients,
                'format' => $schedule->format,
                'frequency' => $schedule->frequency,
            ],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Report schedule created.']);
    }

    public function destroySchedule(string $slug, ReportSchedule $schedule, Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('reports.schedule'), 403);
        abort_unless($schedule->report_slug === $slug, 404);

        app(AuditLogger::class)->record(
            eventType: 'report_schedule.deleted',
            subject: $schedule,
            payload: ['report_slug' => $schedule->report_slug, 'recipients' => $schedule->recipients],
        );

        $schedule->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Report schedule removed.']);
    }

    /**
     * Confine scheduled-report recipients to the configured egress-domain
     * allowlist. Empty allowlist (default) passes any address; dispatch is
     * still audited (NIST AC-4).
     */
    private function recipientDomainRule(): Closure
    {
        $allowed = array_map('strtolower', (array) config('velsa.report_recipient_domains', []));

        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            if ($allowed === []) {
                return;
            }

            $domain = Str::of((string) $value)->afterLast('@')->lower()->value();

            if (! in_array($domain, $allowed, true)) {
                $fail("Recipient domain \"{$domain}\" is not in the approved list for scheduled report delivery.");
            }
        };
    }

    public function exportCsv(string $slug, Request $request): StreamedResponse
    {
        abort_unless($this->registry->has($slug), 404);

        $handler = $this->registry->get($slug);
        $params = $this->resolveParams($handler, $request);
        $result = $handler->run($params);

        $filename = "{$slug}-".DateFormatter::fileStamp().'.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map(fn ($c) => Csv::cell($c['label']), $result->columns));
            foreach ($result->rows as $row) {
                $line = [];
                foreach ($result->columns as $col) {
                    $line[] = Csv::cell($row[$col['key']] ?? '');
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportXlsx(string $slug, Request $request, ReportXlsxExporter $exporter): StreamedResponse
    {
        abort_unless($this->registry->has($slug), 404);

        $handler = $this->registry->get($slug);
        $params = $this->resolveParams($handler, $request);
        $result = $handler->run($params);

        $filename = "{$slug}-".DateFormatter::fileStamp().'.xlsx';
        $body = $exporter->streamToString(
            $handler,
            $result,
            $this->paramRows($handler, $params),
            (string) config('app.name'),
        );

        return response()->streamDownload(
            fn () => print ($body),
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function exportPdf(string $slug, Request $request): PdfBuilder
    {
        abort_unless($this->registry->has($slug), 404);

        $handler = $this->registry->get($slug);
        $params = $this->resolveParams($handler, $request);
        $result = $handler->run($params);

        $filename = "{$slug}-".DateFormatter::fileStamp().'.pdf';

        return Pdf::view('pdf.report', [
            'handler' => $handler,
            'result' => $result,
            'paramRows' => $this->paramRows($handler, $params),
            'appName' => (string) config('app.name'),
            'generatedAt' => $result->generatedAt
                ? DateFormatter::reportStamp(CarbonImmutable::parse($result->generatedAt))
                : DateFormatter::reportStamp(now()),
        ])
            ->landscape()
            ->name($filename);
    }

    /**
     * Flatten param definitions into label/value rows for the PDF header strip.
     * Skips params with no resolved value.
     *
     * @param  array<string, mixed>  $params
     * @return list<array{label:string, value:string}>
     */
    protected function paramRows(ReportHandler $handler, array $params): array
    {
        $rows = [];
        foreach ($handler->parameters() as $param) {
            $value = $params[$param['key']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $rows[] = [
                'label' => $param['label'],
                'value' => (string) $value,
            ];
        }

        return $rows;
    }

    /**
     * Read the declared params the request provides, defaulting anything missing.
     *
     * @return array<string, mixed>
     */
    protected function resolveParams(ReportHandler $handler, Request $request): array
    {
        $params = [];
        foreach ($handler->parameters() as $param) {
            $key = $param['key'];
            $value = $request->input($key, $param['default'] ?? null);
            if ($value === '' || $value === null) {
                $value = $param['default'] ?? null;
            }

            // sanitize by declared type so a malformed param can't 500 the report
            // (e.g. Carbon::parse on a non-date string); fall back to default/null
            if ($value !== null) {
                $type = $param['type'];
                if ($type === 'date' && ! $this->isParsableDate((string) $value)) {
                    $value = $param['default'] ?? null;
                } elseif ($type === 'integer') {
                    $int = filter_var($value, FILTER_VALIDATE_INT);
                    $value = $int === false ? ($param['default'] ?? null) : $int;
                }
            }

            $params[$key] = $value;
        }

        return $params;
    }

    private function isParsableDate(string $value): bool
    {
        try {
            CarbonImmutable::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
