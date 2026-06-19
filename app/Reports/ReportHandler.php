<?php

namespace App\Reports;

interface ReportHandler
{
    public function slug(): string;

    public function category(): string;

    public function title(): string;

    public function description(): string;

    /**
     * Parameter schema; drives filter inputs and validation.
     *
     * @return array<int, array{key: string, label: string, type: string, required?: bool, default?: mixed, options?: array<int, array{value: string|int, label: string}>}>
     */
    public function parameters(): array;

    /**
     * @param  array<string, mixed>  $params
     */
    public function run(array $params): ReportResult;
}
