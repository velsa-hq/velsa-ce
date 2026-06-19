<?php

namespace App\Reports;

use App\Models\ReportDefinition;
use App\Models\ReportRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Executes a ReportDefinition into a ReportResult. Translates builder-config
 * JSON into Eloquent operations, using the datasource descriptor as the trust
 * boundary - only fields/operators it declares are interpolated into the query.
 * Supports flat (raw row dump) and grouped-aggregate (GROUP BY) modes.
 */
class AdHocReportRunner
{
    public function __construct(protected DatasourceRegistry $datasources) {}

    public function run(ReportDefinition $definition, ?int $userId = null): ReportResult
    {
        $start = microtime(true);
        $descriptor = $this->datasources->get($definition->datasource);

        $filters = $definition->filters_json ?? [];
        $dimensions = $definition->dimensions_json ?? [];
        $metrics = $definition->metrics_json ?? [];
        $sort = $definition->sort_json ?? [];
        $rowLimit = (int) ($definition->row_limit ?: 1000);

        $query = $descriptor->query();

        $this->applyFilters($query, $descriptor, $filters);

        $isAggregated = ! empty($dimensions) || ! empty($metrics);

        if ($isAggregated) {
            [$columns, $rows] = $this->runAggregate($query, $descriptor, $dimensions, $metrics, $sort, $rowLimit);
        } else {
            [$columns, $rows] = $this->runFlat($query, $descriptor, $sort, $rowLimit);
        }

        $duration = (int) round((microtime(true) - $start) * 1000);

        // persist a run record (summary only; the full result is recomputed on
        // later loads to keep the table light)
        ReportRun::query()->create([
            'report_definition_id' => $definition->id,
            'params_json' => null,
            'row_count' => count($rows),
            'summary_json' => null,
            'duration_ms' => $duration,
            'generated_by_user_id' => $userId,
            'generated_at' => now(),
        ]);

        return new ReportResult(
            title: $definition->name,
            description: $definition->description ?? '',
            columns: $columns,
            rows: $rows,
            summary: [
                ['label' => 'Rows', 'value' => (string) count($rows)],
                ['label' => 'Duration', 'value' => $duration.' ms'],
            ],
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Apply user-supplied filters. Every field is resolved through the
     * datasource descriptor; any key not in the catalog is dropped (defensive
     * against stale builder config or hand-edited JSON).
     *
     * @param  array<int, array<string, mixed>>  $filters
     */
    protected function applyFilters(Builder $query, Datasources\DatasourceDescriptor $descriptor, array $filters): void
    {
        foreach ($filters as $filter) {
            $field = $descriptor->field($filter['field'] ?? '');
            if ($field === null) {
                continue;
            }

            $operator = (string) ($filter['operator'] ?? '=');
            $value = $filter['value'] ?? null;
            $sql = $field->sqlExpression;

            switch ($operator) {
                case '=':
                case '!=':
                case '>':
                case '<':
                case '>=':
                case '<=':
                    if ($field->type === 'money' && is_numeric($value)) {
                        $value = (int) round(((float) $value) * 100);
                    }
                    $query->whereRaw("{$sql} {$operator} ?", [$value]);
                    break;
                case 'like':
                    $query->whereRaw("{$sql} like ?", ['%'.$value.'%']);
                    break;
                case 'in':
                    if (! is_array($value)) {
                        $value = array_filter(array_map('trim', explode(',', (string) $value)));
                    }
                    if (! empty($value)) {
                        $placeholders = implode(',', array_fill(0, count($value), '?'));
                        $query->whereRaw("{$sql} in ({$placeholders})", array_values($value));
                    }
                    break;
                case 'is_null':
                    $query->whereRaw("{$sql} is null");
                    break;
                case 'is_not_null':
                    $query->whereRaw("{$sql} is not null");
                    break;
                default:
                    // unknown operator -> skip; the UI shouldn't produce one,
                    // but be defensive
            }
        }
    }

    /**
     * Flat (non-aggregated) result - every row from the datasource's
     * default columns, capped at row_limit.
     *
     * @param  array<int, array<string, string>>  $sort
     * @return array{0: list<array{key: string, label: string, align?: string}>, 1: list<array<string, mixed>>}
     */
    protected function runFlat(Builder $query, Datasources\DatasourceDescriptor $descriptor, array $sort, int $limit): array
    {
        $fields = $descriptor->fields();
        $grammar = $query->getQuery()->getGrammar();
        $selects = [];
        foreach ($fields as $key => $field) {
            $selects[] = DB::raw($field->sqlExpression.' as '.$grammar->wrap($key));
        }
        $query->setBindings([], 'select')->select($selects);

        $this->applySort($query, $descriptor, $sort);
        $query->limit($limit);

        $columns = [];
        foreach ($fields as $key => $field) {
            $columns[] = [
                'key' => $key,
                'label' => $field->label,
                'align' => in_array($field->type, ['number', 'money'], true) ? 'right' : 'left',
            ];
        }

        $rows = [];
        foreach ($query->get() as $row) {
            $rowData = [];
            foreach ($fields as $key => $field) {
                $rowData[$key] = $this->formatValue($field, $row->{$key} ?? null);
            }
            $rows[] = $rowData;
        }

        return [$columns, $rows];
    }

    /**
     * Aggregated result - GROUP BY the dimensions, apply the metrics.
     *
     * @param  array<int, string>  $dimensions  field keys
     * @param  array<int, array<string, string>>  $metrics  [{field, aggregation, label?}]
     * @param  array<int, array<string, string>>  $sort
     * @return array{0: list<array{key: string, label: string, align?: string}>, 1: list<array<string, mixed>>}
     */
    protected function runAggregate(
        Builder $query,
        Datasources\DatasourceDescriptor $descriptor,
        array $dimensions,
        array $metrics,
        array $sort,
        int $limit,
    ): array {
        $allowedAggregations = ['count', 'sum', 'avg', 'min', 'max'];

        $grammar = $query->getQuery()->getGrammar();
        $selects = [];
        $groupBys = [];
        $columns = [];

        foreach ($dimensions as $dimKey) {
            $field = $descriptor->field($dimKey);
            if ($field === null) {
                continue;
            }
            $selects[] = DB::raw($field->sqlExpression.' as '.$grammar->wrap($dimKey));
            $groupBys[] = DB::raw($field->sqlExpression);
            $columns[] = ['key' => $dimKey, 'label' => $field->label];
        }

        foreach ($metrics as $i => $metric) {
            $field = $descriptor->field($metric['field'] ?? '');
            $agg = strtolower((string) ($metric['aggregation'] ?? 'count'));
            if (! in_array($agg, $allowedAggregations, true)) {
                throw new InvalidArgumentException("Unknown aggregation: {$agg}");
            }
            // count(*) needs no specific field
            $sqlExpr = ($field === null && $agg === 'count')
                ? '*'
                : ($field?->sqlExpression ?? null);
            if ($sqlExpr === null) {
                continue;
            }
            $alias = "m{$i}_{$agg}";
            $selects[] = DB::raw(strtoupper($agg).'('.$sqlExpr.') as '.$grammar->wrap($alias));

            $label = $metric['label'] ?? ucfirst($agg).' of '.($field?->label ?? '*');
            $align = ($field && $field->type === 'money') || in_array($agg, ['count', 'sum', 'avg'], true)
                ? 'right'
                : 'left';
            $columns[] = ['key' => $alias, 'label' => $label, 'align' => $align];
        }

        $query->setBindings([], 'select')->select($selects);
        if (! empty($groupBys)) {
            $query->groupBy(...$groupBys);
        }

        $this->applySort($query, $descriptor, $sort);
        $query->limit($limit);

        $rows = [];
        foreach ($query->get() as $r) {
            $rowOut = [];
            foreach ($columns as $col) {
                $val = $r->{$col['key']} ?? null;
                // metric columns stay numeric; dimension columns get formatted
                if (str_starts_with($col['key'], 'm')) {
                    $rowOut[$col['key']] = is_numeric($val) ? (float) $val : $val;
                } else {
                    $field = $descriptor->field($col['key']);
                    $rowOut[$col['key']] = $field ? $this->formatValue($field, $val) : $val;
                }
            }
            $rows[] = $rowOut;
        }

        return [$columns, $rows];
    }

    /**
     * @param  array<int, array<string, string>>  $sort
     */
    protected function applySort(Builder $query, Datasources\DatasourceDescriptor $descriptor, array $sort): void
    {
        foreach ($sort as $s) {
            $field = $descriptor->field($s['field'] ?? '');
            if ($field === null) {
                continue;
            }
            $direction = strtolower($s['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderByRaw("{$field->sqlExpression} {$direction}");
        }
    }

    protected function formatValue(DatasourceField $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'money' => number_format((int) $value / 100, 2, '.', ''),
            'date' => is_string($value) ? substr($value, 0, 10) : $value,
            default => $value,
        };
    }
}
