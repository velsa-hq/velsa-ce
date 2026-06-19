<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExportFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExportTemplateStoreRequest;
use App\Http\Requests\ExportTemplateUpdateRequest;
use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Models\Venue;
use App\Services\Accounting\ExportSource;
use App\Services\Accounting\ExportTemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ExportTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = ExportTemplate::query()
            ->withCount(['columns', 'batches'])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (ExportTemplate $t) => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'description' => $t->description,
                'format' => $t->format?->value,
                'format_label' => $t->format?->label(),
                'is_default' => $t->is_default,
                'file_extension' => $t->file_extension,
                'column_count' => $t->columns_count ?? 0,
                'batch_count' => $t->batches_count ?? 0,
                'updated_at' => $t->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/export-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/export-templates/create', [
            'metadata' => $this->formMetadata(),
            'template' => $this->emptyTemplatePayload(),
        ]);
    }

    public function store(ExportTemplateStoreRequest $request): RedirectResponse
    {
        $data = $request->preparedForModel();
        $columns = $data['columns'];
        unset($data['columns']);
        $data['created_by_user_id'] = $request->user()?->id;

        $template = DB::transaction(function () use ($data, $columns) {
            /** @var ExportTemplate $template */
            $template = ExportTemplate::query()->create($data);
            $this->syncColumns($template, $columns);

            return $template;
        });

        return redirect()
            ->route('admin.export-templates.edit', $template)
            ->with('toast', ['type' => 'success', 'message' => "Created template \"{$template->name}\"."]);
    }

    public function edit(ExportTemplate $template, ExportTemplateRenderer $renderer): Response
    {
        $template->load('columns');

        return Inertia::render('admin/export-templates/edit', [
            'metadata' => $this->formMetadata(),
            'template' => $this->serializeTemplate($template),
            'preview' => $this->renderSamplePreview($template, $renderer),
        ]);
    }

    public function update(ExportTemplateUpdateRequest $request, ExportTemplate $template): RedirectResponse
    {
        $data = $request->preparedForModel();
        $columns = $data['columns'];
        unset($data['columns']);

        DB::transaction(function () use ($template, $data, $columns) {
            $template->update($data);
            $this->syncColumns($template, $columns);
        });

        return redirect()
            ->route('admin.export-templates.edit', $template)
            ->with('toast', ['type' => 'success', 'message' => "Updated template \"{$template->name}\"."]);
    }

    public function setDefault(ExportTemplate $template): RedirectResponse
    {
        $template->update(['is_default' => true]);

        return back()->with('toast', [
            'type' => 'success',
            'message' => "\"{$template->name}\" is now the default export template.",
        ]);
    }

    public function destroy(ExportTemplate $template): RedirectResponse
    {
        // FK from ledger_export_batches.export_template_id - check first so the
        // delete can't abort the surrounding transaction on a constraint violation
        if (LedgerExportBatch::query()->where('export_template_id', $template->id)->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => "Cannot delete \"{$template->name}\" - historical export batches reference it. Mark another template as default and retire this one from the seeder instead.",
            ]);
        }

        $template->delete();

        return redirect()
            ->route('admin.export-templates.index')
            ->with('toast', ['type' => 'success', 'message' => "Deleted template \"{$template->name}\"."]);
    }

    /**
     * Render an unsaved template draft against sample data for the editor preview pane.
     */
    public function preview(Request $request, ExportTemplateRenderer $renderer): JsonResponse
    {
        $draft = $request->input('template', []);
        $columns = $draft['columns'] ?? [];

        if (empty($columns)) {
            return response()->json(['payload' => '(no columns configured)']);
        }

        // in-memory ExportTemplate, never saved
        $template = new ExportTemplate([
            'name' => $draft['name'] ?? 'Preview',
            'slug' => 'preview',
            'format' => $draft['format'] ?? ExportFormat::Csv->value,
            'delimiter' => $draft['delimiter'] ?? ',',
            'quote_char' => $draft['quote_char'] ?? '"',
            'line_ending' => ($draft['line_ending'] ?? null) === 'crlf' ? "\r\n" : (($draft['line_ending'] ?? null) === 'lf' ? "\n" : ($draft['line_ending'] ?? "\n")),
            'include_header' => (bool) ($draft['include_header'] ?? true),
            'include_footer' => (bool) ($draft['include_footer'] ?? false),
            'file_extension' => $draft['file_extension'] ?? 'csv',
        ]);
        $template->id = 0;

        $columnModels = collect($columns)->values()->map(function (array $row, int $idx) {
            $col = new ExportTemplateColumn([
                'sort_order' => $idx + 1,
                'label' => $row['label'] ?? "col_{$idx}",
                'source' => $row['source'] ?? ExportSource::ACCOUNT_CODE,
                'format_mask' => $row['format_mask'] ?? null,
                'default_value' => $row['default_value'] ?? null,
                'width' => isset($row['width']) ? (int) $row['width'] : null,
                'align' => $row['align'] ?? 'left',
                'pad_char' => $row['pad_char'] ?? ' ',
            ]);
            $col->id = $idx + 1;

            return $col;
        });
        // stub the relation so the renderer reads this in-memory collection
        $template->setRelation('columns', $columnModels);

        try {
            $payload = $renderer->render(
                $template,
                $this->sampleBatch(),
                $this->sampleEntries(),
            );
        } catch (Throwable $e) {
            return response()->json(['payload' => '⚠ Preview error: '.$e->getMessage()], 200);
        }

        return response()->json(['payload' => $payload]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function syncColumns(ExportTemplate $template, array $columns): void
    {
        $template->columns()->delete();
        foreach (array_values($columns) as $idx => $col) {
            $template->columns()->create([
                'sort_order' => $idx + 1,
                'label' => $col['label'],
                'source' => $col['source'],
                'format_mask' => $col['format_mask'] ?: null,
                'default_value' => $col['default_value'] ?: null,
                'width' => isset($col['width']) && $col['width'] !== '' ? (int) $col['width'] : null,
                'align' => $col['align'] ?? 'left',
                'pad_char' => $col['pad_char'] ?? ' ',
            ]);
        }
    }

    /**
     * Render the template against a synthetic batch for preview.
     */
    protected function renderSamplePreview(ExportTemplate $template, ExportTemplateRenderer $renderer): string
    {
        try {
            return $renderer->render(
                $template,
                $this->sampleBatch(),
                $this->sampleEntries(),
            );
        } catch (Throwable $e) {
            return '⚠ Preview error: '.$e->getMessage();
        }
    }

    protected function sampleBatch(): LedgerExportBatch
    {
        $batch = new LedgerExportBatch([
            'period' => now()->format('Y-m'),
            'status' => 'preview',
            'entry_count' => 2,
        ]);
        $batch->id = 9999;

        return $batch;
    }

    /**
     * @return Collection<int, JournalEntry>
     */
    protected function sampleEntries(): Collection
    {
        $venueName = Venue::query()->orderBy('id')->value('name') ?? 'Sample Venue';

        $e1 = new JournalEntry([
            'account_code' => '1010',
            'fund_code' => 'TOURISM',
            'description' => 'Sample debit - convention deposit',
            'debit_cents' => 250000,
            'credit_cents' => 0,
            'posted_on' => now()->subDays(2)->toDateString(),
        ]);
        $e1->id = 1;
        $e1->source_type = 'App\\Models\\Booking';
        $e1->source_id = 1;
        $e1->setRelation('venue', (object) ['name' => $venueName]);

        $e2 = new JournalEntry([
            'account_code' => '4200',
            'fund_code' => 'TOURISM',
            'description' => 'Sample credit - recognized revenue',
            'debit_cents' => 0,
            'credit_cents' => 250000,
            'posted_on' => now()->subDays(2)->toDateString(),
        ]);
        $e2->id = 2;
        $e2->source_type = 'App\\Models\\Booking';
        $e2->source_id = 1;
        $e2->setRelation('venue', (object) ['name' => $venueName]);

        return collect([$e1, $e2]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formMetadata(): array
    {
        return [
            'formats' => array_map(
                fn (ExportFormat $f) => ['value' => $f->value, 'label' => $f->label()],
                ExportFormat::cases(),
            ),
            'sources' => ExportSource::options(),
            'format_masks' => [
                ['value' => 'date:Y-m-d', 'label' => 'Date (Y-m-d)'],
                ['value' => 'date:Ymd', 'label' => 'Date (Ymd)'],
                ['value' => 'date:m/d/Y', 'label' => 'Date (m/d/Y)'],
                ['value' => 'money:dot', 'label' => 'Money (123.45)'],
                ['value' => 'money:int', 'label' => 'Money (cents int)'],
                ['value' => 'money:signed', 'label' => 'Money (signed)'],
                ['value' => 'money:dollars', 'label' => 'Money (whole dollars)'],
                ['value' => 'upper', 'label' => 'UPPERCASE'],
                ['value' => 'lower', 'label' => 'lowercase'],
                ['value' => 'trim', 'label' => 'Trim whitespace'],
                ['value' => 'pad-zero:6', 'label' => 'Pad zeros (6 wide)'],
                ['value' => 'truncate:50', 'label' => 'Truncate (50 chars)'],
            ],
            'line_endings' => [
                ['value' => 'lf', 'label' => 'LF (Unix)'],
                ['value' => 'crlf', 'label' => 'CRLF (Windows)'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyTemplatePayload(): array
    {
        return [
            'id' => null,
            'slug' => '',
            'name' => '',
            'description' => '',
            'format' => ExportFormat::Csv->value,
            'delimiter' => ',',
            'quote_char' => '"',
            'line_ending' => 'lf',
            'encoding' => 'utf-8',
            'include_header' => true,
            'include_footer' => false,
            'is_default' => false,
            'file_extension' => 'csv',
            'columns' => [
                ['label' => 'JournalNumber', 'source' => ExportSource::BATCH_PREFIX, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
                ['label' => 'LineNumber', 'source' => ExportSource::LINE_NUMBER, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
                ['label' => 'Date', 'source' => ExportSource::POSTED_ON, 'format_mask' => 'date:Y-m-d', 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
                ['label' => 'Account', 'source' => ExportSource::ACCOUNT_CODE, 'format_mask' => null, 'default_value' => null, 'width' => null, 'align' => 'left', 'pad_char' => ' '],
                ['label' => 'Debit', 'source' => ExportSource::DEBIT_CENTS, 'format_mask' => 'money:dot', 'default_value' => null, 'width' => null, 'align' => 'right', 'pad_char' => ' '],
                ['label' => 'Credit', 'source' => ExportSource::CREDIT_CENTS, 'format_mask' => 'money:dot', 'default_value' => null, 'width' => null, 'align' => 'right', 'pad_char' => ' '],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeTemplate(ExportTemplate $template): array
    {
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'description' => $template->description,
            'format' => $template->format?->value,
            'delimiter' => $template->delimiter,
            'quote_char' => $template->quote_char,
            'line_ending' => $template->line_ending === "\r\n" ? 'crlf' : 'lf',
            'encoding' => $template->encoding,
            'include_header' => $template->include_header,
            'include_footer' => $template->include_footer,
            'is_default' => $template->is_default,
            'file_extension' => $template->file_extension,
            'columns' => $template->columns->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'source' => $c->source,
                'format_mask' => $c->format_mask,
                'default_value' => $c->default_value,
                'width' => $c->width,
                'align' => $c->align,
                'pad_char' => $c->pad_char,
            ])->all(),
        ];
    }
}
