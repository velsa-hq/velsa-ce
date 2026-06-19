<?php

namespace App\Reports\Datasources;

use App\Reports\DatasourceField;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Builder;

/**
 * fields() is the allowlist - user-supplied filter/metric keys are validated
 * against it so raw input never reaches SQL as an identifier.
 * aggregations() stays short to keep SQL functions allowlisted.
 */
abstract class DatasourceDescriptor
{
    abstract public function key(): ReportDatasource;

    abstract public function label(): string;

    /**
     * Base query - runner attaches filters/groupings. Apply default joins/scoping here.
     */
    abstract public function query(): Builder;

    /**
     * @return array<string, DatasourceField> keyed by field key
     */
    abstract public function fields(): array;

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function aggregations(): array
    {
        return [
            ['value' => 'count', 'label' => 'Count'],
            ['value' => 'sum', 'label' => 'Sum'],
            ['value' => 'avg', 'label' => 'Average'],
            ['value' => 'min', 'label' => 'Minimum'],
            ['value' => 'max', 'label' => 'Maximum'],
        ];
    }

    public function field(string $key): ?DatasourceField
    {
        return $this->fields()[$key] ?? null;
    }
}
