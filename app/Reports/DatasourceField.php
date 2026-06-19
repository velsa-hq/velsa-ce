<?php

namespace App\Reports;

/**
 * A selectable/filterable/groupable field on a datasource.
 *
 * `type` drives the UI control and the value cast:
 *   - string : like/= operators
 *   - number : comparison operators
 *   - money  : numeric, scaled by 100 (stored as cents)
 *   - date   : date picker
 *   - bool   : true/false
 *   - enum   : select with `options`
 */
final class DatasourceField
{
    /**
     * @param  array<int, array{value: string, label: string}>  $options  for type=enum
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly string $sqlExpression,
        public readonly array $options = [],
        public readonly bool $aggregatable = false,
    ) {}

    /**
     * Operators valid for this field's type.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function operators(): array
    {
        return match ($this->type) {
            'string' => [
                ['value' => '=', 'label' => 'equals'],
                ['value' => '!=', 'label' => 'not equals'],
                ['value' => 'like', 'label' => 'contains'],
                ['value' => 'is_null', 'label' => 'is empty'],
                ['value' => 'is_not_null', 'label' => 'is set'],
            ],
            'number', 'money' => [
                ['value' => '=', 'label' => 'equals'],
                ['value' => '!=', 'label' => 'not equals'],
                ['value' => '>', 'label' => 'greater than'],
                ['value' => '<', 'label' => 'less than'],
                ['value' => '>=', 'label' => 'at least'],
                ['value' => '<=', 'label' => 'at most'],
            ],
            'date' => [
                ['value' => '>=', 'label' => 'on or after'],
                ['value' => '<=', 'label' => 'on or before'],
                ['value' => 'is_null', 'label' => 'is empty'],
                ['value' => 'is_not_null', 'label' => 'is set'],
            ],
            'bool' => [
                ['value' => '=', 'label' => 'is'],
            ],
            'enum' => [
                ['value' => '=', 'label' => 'is'],
                ['value' => '!=', 'label' => 'is not'],
                ['value' => 'in', 'label' => 'is one of'],
            ],
            default => [['value' => '=', 'label' => 'equals']],
        };
    }
}
