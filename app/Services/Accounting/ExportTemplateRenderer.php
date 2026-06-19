<?php

namespace App\Services\Accounting;

use App\Enums\ExportFormat;
use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Renders an ExportTemplate + a batch's entries to the output string.
 * Per cell: resolve source -> format mask -> default if empty ->
 * csv-escape or pad-to-width.
 */
class ExportTemplateRenderer
{
    public function render(ExportTemplate $template, LedgerExportBatch $batch, iterable $entries): string
    {
        $columns = $template->columns instanceof Collection
            ? $template->columns
            : $template->columns()->get();

        if ($columns->isEmpty()) {
            throw new RuntimeException("Template '{$template->slug}' has no columns configured.");
        }

        $lines = [];

        if ($template->include_header) {
            $lines[] = $this->renderHeader($template, $columns);
        }

        $lineNumber = 1;
        foreach ($entries as $entry) {
            $lines[] = $this->renderRow($template, $columns, $entry, $batch, $lineNumber);
            $lineNumber++;
        }

        if ($template->include_footer) {
            $lines[] = $this->renderFooter($template, $columns, $entries, $batch);
        }

        return implode($template->line_ending, $lines).$template->line_ending;
    }

    /**
     * @param  Collection<int, ExportTemplateColumn>  $columns
     */
    protected function renderHeader(ExportTemplate $template, Collection $columns): string
    {
        $labels = $columns->map(fn (ExportTemplateColumn $c) => $c->label)->all();

        return match ($template->format) {
            ExportFormat::Csv => $this->joinCsv($template, $labels),
            ExportFormat::FixedWidth => $this->joinFixedWidth($columns, $labels),
        };
    }

    /**
     * @param  Collection<int, ExportTemplateColumn>  $columns
     */
    protected function renderRow(
        ExportTemplate $template,
        Collection $columns,
        JournalEntry $entry,
        LedgerExportBatch $batch,
        int $lineNumber,
    ): string {
        $values = $columns->map(function (ExportTemplateColumn $col) use ($entry, $batch, $lineNumber) {
            $raw = ExportSource::resolve($col->source, $entry, $batch, ['line_number' => $lineNumber]);
            $formatted = ValueFormatter::apply($raw, $col->format_mask);

            if ($formatted === '' && ($col->default_value ?? '') !== '') {
                return (string) $col->default_value;
            }

            return $formatted;
        })->all();

        return match ($template->format) {
            ExportFormat::Csv => $this->joinCsv($template, $values),
            ExportFormat::FixedWidth => $this->joinFixedWidth($columns, $values),
        };
    }

    /**
     * Batch-totals checksum row for downstream importers.
     *
     * @param  Collection<int, ExportTemplateColumn>  $columns
     * @param  iterable<JournalEntry>  $entries
     */
    protected function renderFooter(
        ExportTemplate $template,
        Collection $columns,
        iterable $entries,
        LedgerExportBatch $batch,
    ): string {
        $debits = 0;
        $credits = 0;
        $count = 0;
        foreach ($entries as $e) {
            $debits += (int) $e->debit_cents;
            $credits += (int) $e->credit_cents;
            $count++;
        }

        return sprintf(
            'BATCH_TOTAL,%s,%d,%s,%s',
            $batch->period,
            $count,
            number_format($debits / 100, 2, '.', ''),
            number_format($credits / 100, 2, '.', ''),
        );
    }

    /**
     * @param  array<int, string>  $values
     */
    protected function joinCsv(ExportTemplate $template, array $values): string
    {
        $escaped = array_map(
            fn (string $v) => ValueFormatter::csvEscape($v, $template->delimiter, $template->quote_char),
            $values,
        );

        return implode($template->delimiter, $escaped);
    }

    /**
     * @param  Collection<int, ExportTemplateColumn>  $columns
     * @param  array<int, string>  $values
     */
    protected function joinFixedWidth(Collection $columns, array $values): string
    {
        $out = '';
        foreach ($columns->values() as $idx => $col) {
            $width = $col->width ?? mb_strlen($values[$idx] ?? '');
            $out .= ValueFormatter::fixedWidth(
                value: $values[$idx] ?? '',
                width: $width,
                align: $col->align ?? 'left',
                padChar: $col->pad_char ?? ' ',
            );
        }

        return $out;
    }
}
