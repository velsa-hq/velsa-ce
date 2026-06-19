<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReportDefinition;
use App\Reports\AdHocReportRunner;
use App\Reports\DatasourceRegistry;
use App\Reports\ReportDatasource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportBuilderController extends Controller
{
    public function index(): Response
    {
        $definitions = ReportDefinition::query()
            ->with('creator:id,name')
            ->withCount('runs')
            ->orderBy('name')
            ->get()
            ->map(fn (ReportDefinition $d) => [
                'id' => $d->id,
                'slug' => $d->slug,
                'name' => $d->name,
                'description' => $d->description,
                'datasource' => $d->datasource?->value,
                'datasource_label' => $d->datasource?->label(),
                'created_by' => $d->creator?->name,
                'updated_at' => $d->updated_at?->toIso8601String(),
                'runs_count' => $d->runs_count ?? 0,
            ]);

        return Inertia::render('admin/report-builder/index', [
            'definitions' => $definitions,
        ]);
    }

    public function create(DatasourceRegistry $registry): Response
    {
        return Inertia::render('admin/report-builder/create', [
            'catalog' => $registry->catalog(),
            'definition' => $this->emptyDefinition(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDefinition($request);
        $data['created_by_user_id'] = $request->user()?->id;

        $def = ReportDefinition::query()->create($data);

        return redirect()
            ->route('admin.report-builder.show', $def)
            ->with('toast', ['type' => 'success', 'message' => "Created report \"{$def->name}\"."]);
    }

    public function show(ReportDefinition $definition, AdHocReportRunner $runner, Request $request): Response
    {
        $result = null;
        $error = null;
        try {
            $r = $runner->run($definition, $request->user()?->id);
            $result = [
                'columns' => $r->columns,
                'rows' => $r->rows,
                'summary' => $r->summary,
                'generated_at' => $r->generatedAt,
            ];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('admin/report-builder/show', [
            'definition' => $this->serializeDefinition($definition),
            'result' => $result,
            'error' => $error,
        ]);
    }

    public function edit(ReportDefinition $definition, DatasourceRegistry $registry): Response
    {
        return Inertia::render('admin/report-builder/edit', [
            'catalog' => $registry->catalog(),
            'definition' => $this->serializeDefinition($definition),
        ]);
    }

    public function update(Request $request, ReportDefinition $definition): RedirectResponse
    {
        $data = $this->validateDefinition($request, $definition);
        $definition->update($data);

        return redirect()
            ->route('admin.report-builder.show', $definition)
            ->with('toast', ['type' => 'success', 'message' => "Updated \"{$definition->name}\"."]);
    }

    public function destroy(ReportDefinition $definition): RedirectResponse
    {
        $name = $definition->name;
        $definition->delete();

        return redirect()
            ->route('admin.report-builder.index')
            ->with('toast', ['type' => 'success', 'message' => "Deleted \"{$name}\"."]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDefinition(Request $request, ?ReportDefinition $existing = null): array
    {
        $sources = array_map(fn (ReportDatasource $d) => $d->value, ReportDatasource::cases());

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('report_definitions', 'slug')->ignore($existing?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'datasource' => ['required', Rule::in($sources)],
            'filters_json' => ['nullable', 'array'],
            'filters_json.*.field' => ['required_with:filters_json', 'string', 'max:60'],
            'filters_json.*.operator' => ['required_with:filters_json', 'string', 'max:20'],
            'filters_json.*.value' => ['nullable'],
            'dimensions_json' => ['nullable', 'array'],
            'dimensions_json.*' => ['string', 'max:60'],
            'metrics_json' => ['nullable', 'array'],
            'metrics_json.*.field' => ['nullable', 'string', 'max:60'],
            'metrics_json.*.aggregation' => ['required_with:metrics_json', Rule::in(['count', 'sum', 'avg', 'min', 'max'])],
            'metrics_json.*.label' => ['nullable', 'string', 'max:60'],
            'sort_json' => ['nullable', 'array'],
            'sort_json.*.field' => ['required_with:sort_json', 'string', 'max:60'],
            'sort_json.*.direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'row_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDefinition(): array
    {
        return [
            'id' => null,
            'slug' => '',
            'name' => '',
            'description' => '',
            'datasource' => ReportDatasource::Bookings->value,
            'filters_json' => [],
            'dimensions_json' => [],
            'metrics_json' => [],
            'sort_json' => [],
            'row_limit' => 1000,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDefinition(ReportDefinition $def): array
    {
        return [
            'id' => $def->id,
            'slug' => $def->slug,
            'name' => $def->name,
            'description' => $def->description,
            'datasource' => $def->datasource?->value,
            'datasource_label' => $def->datasource?->label(),
            'filters_json' => $def->filters_json ?? [],
            'dimensions_json' => $def->dimensions_json ?? [],
            'metrics_json' => $def->metrics_json ?? [],
            'sort_json' => $def->sort_json ?? [],
            'row_limit' => $def->row_limit,
        ];
    }
}
