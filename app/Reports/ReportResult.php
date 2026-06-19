<?php

namespace App\Reports;

/**
 * Shape of a report's output, serialized to the Inertia page and CSV.
 *
 * @phpstan-type ReportColumn array{key: string, label: string, format?: string, align?: string}
 * @phpstan-type ReportSummaryEntry array{label: string, value: string, hint?: string}
 */
final class ReportResult
{
    /**
     * @param  array<int, ReportColumn>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, ReportSummaryEntry>  $summary
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $summary = [],
        public readonly ?string $generatedAt = null,
    ) {}
}
