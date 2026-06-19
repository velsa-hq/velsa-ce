<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Models\ImportJob;
use App\Services\Import\ImportRegistry;
use App\Services\Import\ImportService;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV import flow: upload, map columns, preview, commit, reverse. Logic in ImportService.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $service,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/imports/index', [
            'kinds' => array_map(fn ($i) => [
                'key' => $i->key(),
                'label' => $i->label(),
                'description' => $i->description(),
                'requires_read_only' => $i->requiresReadOnly(),
            ], $this->registry->all()),
            'jobs' => ImportJob::query()
                ->with('creator:id,name')
                ->latest()
                ->limit(25)
                ->get()
                ->map(fn (ImportJob $j) => $this->presentSummary($j)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_map(fn ($i) => $i->key(), $this->registry->all()))],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.(int) config('import.max_file_size_kb', 10240)],
            'has_header' => ['boolean'],
            'delimiter' => ['nullable', 'string', Rule::in([',', ';', '|', "\t", 'tab'])],
        ]);

        $delimiter = ($data['delimiter'] ?? ',') === 'tab' ? "\t" : ($data['delimiter'] ?? ',');

        $path = $request->file('file')->store(
            (string) config('import.directory', 'imports'),
            (string) config('import.disk', 'local'),
        );

        $job = ImportJob::query()->create([
            'kind' => $data['kind'],
            'status' => ImportStatus::Pending,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'disk' => (string) config('import.disk', 'local'),
            'file_path' => $path,
            'has_header' => $data['has_header'] ?? true,
            'delimiter' => $delimiter,
            'created_by_user_id' => $request->user()?->id,
        ]);

        // guess the mapping from the file's headers
        $importer = $this->registry->getOrFail($job->kind);
        $job->update(['column_map' => $this->service->autoMap($importer, $this->service->headers($job))]);

        return to_route('admin.imports.show', $job)
            ->with('toast', ['type' => 'success', 'message' => 'File uploaded - map the columns, then preview.']);
    }

    public function show(ImportJob $importJob): Response
    {
        $importer = $this->registry->getOrFail($importJob->kind);

        return Inertia::render('admin/imports/show', [
            'job' => $this->presentDetail($importJob),
            'fields' => array_map(fn ($f) => [
                'key' => $f->key,
                'label' => $f->label,
                'required' => $f->required,
                'hint' => $f->hint,
            ], $importer->fields()),
            'headers' => $this->service->headers($importJob),
            'requires_read_only' => $importer->requiresReadOnly(),
            'errors_sample' => $importJob->errors()
                ->orderBy('row_number')->orderBy('id')
                ->limit((int) config('import.error_sample_limit', 50))
                ->get(['row_number', 'field', 'message']),
        ]);
    }

    public function preview(Request $request, ImportJob $importJob): RedirectResponse
    {
        $this->assertNotTerminal($importJob);

        $importer = $this->registry->getOrFail($importJob->kind);
        $headers = $this->service->headers($importJob);

        $data = $request->validate([
            'column_map' => ['required', 'array'],
            'column_map.*' => ['nullable', 'string', Rule::in($headers)],
        ]);

        $map = $data['column_map'];

        // every required field must be mapped before a dry run is meaningful
        $missing = [];
        foreach ($importer->fields() as $field) {
            if ($field->required && empty($map[$field->key])) {
                $missing[] = $field->label;
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'column_map' => 'Map the required field(s) first: '.implode(', ', $missing).'.',
            ]);
        }

        $importJob->update(['column_map' => $map]);
        $summary = $this->service->preview($importJob);

        return back()->with('toast', [
            'type' => $summary['errors'] > 0 ? 'warning' : 'success',
            'message' => "Preview: {$summary['valid']} of {$summary['total']} rows valid, {$summary['errors']} with errors.",
        ]);
    }

    public function commit(ImportJob $importJob): RedirectResponse
    {
        if ($importJob->status !== ImportStatus::Previewed) {
            return back()->with('toast', ['type' => 'error', 'message' => 'Preview the import before committing.']);
        }

        if ($importJob->valid_rows < 1) {
            return back()->with('toast', ['type' => 'error', 'message' => 'No valid rows to import - fix the file or mapping and preview again.']);
        }

        $readOnly = (bool) app(SystemSettings::class)->get('operations.read_only', false);

        // high-risk kinds require read-only mode so nothing shifts under the
        // import; reversal is only clean when it was on
        $importer = $this->registry->getOrFail($importJob->kind);
        if ($importer->requiresReadOnly() && ! $readOnly) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "Enable read-only mode (Admin -> System settings) before importing {$importer->label()} - it changes the live dataset.",
            ]);
        }

        $summary = $this->service->commit($importJob, readOnlyCovered: $readOnly);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Imported {$summary['created']} rows ({$summary['errors']} failed).",
        ]);
    }

    public function reverse(ImportJob $importJob): RedirectResponse
    {
        if (! $importJob->isReversible()) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This import can no longer be reversed.']);
        }

        $summary = $this->service->reverse($importJob);

        $message = "Reversed {$summary['deleted']} rows.";
        if ($summary['skipped'] > 0) {
            $message .= " {$summary['skipped']} left in place (now referenced elsewhere).";
        }

        return back()->with('toast', [
            'type' => $summary['skipped'] > 0 ? 'warning' : 'success',
            'message' => $message,
        ]);
    }

    public function errors(ImportJob $importJob): StreamedResponse
    {
        $csv = $this->service->errorCsv($importJob);
        $filename = "import-{$importJob->id}-errors.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function destroy(ImportJob $importJob): RedirectResponse
    {
        Storage::disk($importJob->disk)->delete($importJob->file_path);
        $importJob->delete();

        return to_route('admin.imports.index')
            ->with('toast', ['type' => 'success', 'message' => 'Import deleted.']);
    }

    private function assertNotTerminal(ImportJob $job): void
    {
        if ($job->status->isTerminal()) {
            throw ValidationException::withMessages([
                'column_map' => 'This import is '.$job->status->label().' and can no longer be re-mapped.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSummary(ImportJob $j): array
    {
        return [
            'id' => $j->id,
            'kind' => $j->kind,
            'kind_label' => $this->registry->get($j->kind)?->label() ?? $j->kind,
            'status' => $j->status->value,
            'status_label' => $j->status->label(),
            'original_filename' => $j->original_filename,
            'total_rows' => $j->total_rows,
            'valid_rows' => $j->valid_rows,
            'created_rows' => $j->created_rows,
            'error_rows' => $j->error_rows,
            'created_by' => $j->creator?->name,
            'created_at' => $j->created_at?->toDateTimeString(),
            'is_reversible' => $j->isReversible(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(ImportJob $j): array
    {
        return array_merge($this->presentSummary($j), [
            'has_header' => $j->has_header,
            'delimiter' => $j->delimiter,
            'column_map' => $j->column_map ?? [],
            'read_only_covered' => $j->read_only_covered,
            'previewed_at' => $j->previewed_at?->toDateTimeString(),
            'committed_at' => $j->committed_at?->toDateTimeString(),
            'reversed_at' => $j->reversed_at?->toDateTimeString(),
        ]);
    }
}
