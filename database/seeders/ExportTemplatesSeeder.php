<?php

namespace Database\Seeders;

use App\Enums\ExportFormat;
use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Services\Accounting\ExportSource;
use Illuminate\Database\Seeder;

/**
 * Two starter GL-export templates (CSV default + fixed-width sample).
 * Idempotent on slug.
 */
class ExportTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGenericCsv();
        $this->seedFixedWidthSample();
    }

    protected function seedGenericCsv(): void
    {
        $template = ExportTemplate::query()->updateOrCreate(
            ['slug' => 'ledger-generic-csv'],
            [
                'name' => 'General Ledger CSV',
                'description' => 'Default journal export - comma-delimited, header row, eight columns in a standard GL column order.',
                'format' => ExportFormat::Csv->value,
                'delimiter' => ',',
                'quote_char' => '"',
                'line_ending' => "\n",
                'encoding' => 'utf-8',
                'include_header' => true,
                'include_footer' => false,
                'is_default' => true,
                'file_extension' => 'csv',
            ],
        );

        $columns = [
            ['sort_order' => 1, 'label' => 'JournalNumber', 'source' => ExportSource::BATCH_PREFIX],
            ['sort_order' => 2, 'label' => 'LineNumber', 'source' => ExportSource::LINE_NUMBER],
            ['sort_order' => 3, 'label' => 'Date', 'source' => ExportSource::POSTED_ON, 'format_mask' => 'date:Y-m-d'],
            ['sort_order' => 4, 'label' => 'Account', 'source' => ExportSource::ACCOUNT_CODE],
            ['sort_order' => 5, 'label' => 'Fund', 'source' => ExportSource::FUND_CODE],
            ['sort_order' => 6, 'label' => 'Description', 'source' => ExportSource::DESCRIPTION],
            ['sort_order' => 7, 'label' => 'Debit', 'source' => ExportSource::DEBIT_CENTS, 'format_mask' => 'money:dot'],
            ['sort_order' => 8, 'label' => 'Credit', 'source' => ExportSource::CREDIT_CENTS, 'format_mask' => 'money:dot'],
        ];

        $this->syncColumns($template, $columns);
    }

    protected function seedFixedWidthSample(): void
    {
        $template = ExportTemplate::query()->updateOrCreate(
            ['slug' => 'ledger-fixed-width-sample'],
            [
                'name' => 'General Ledger Fixed-Width Sample',
                'description' => 'Demonstration fixed-width template. Useful starting point if a GL system\'s spec requires a positional format rather than CSV.',
                'format' => ExportFormat::FixedWidth->value,
                'line_ending' => "\n",
                'encoding' => 'utf-8',
                'include_header' => false,
                'include_footer' => false,
                'is_default' => false,
                'file_extension' => 'txt',
            ],
        );

        $columns = [
            ['sort_order' => 1, 'label' => 'JournalNumber', 'source' => ExportSource::BATCH_PREFIX, 'width' => 20, 'align' => 'left'],
            ['sort_order' => 2, 'label' => 'LineNumber', 'source' => ExportSource::LINE_NUMBER, 'format_mask' => 'pad-zero:5', 'width' => 5, 'align' => 'right', 'pad_char' => '0'],
            ['sort_order' => 3, 'label' => 'Date', 'source' => ExportSource::POSTED_ON, 'format_mask' => 'date:Ymd', 'width' => 8, 'align' => 'left'],
            ['sort_order' => 4, 'label' => 'Account', 'source' => ExportSource::ACCOUNT_CODE, 'width' => 10, 'align' => 'left'],
            ['sort_order' => 5, 'label' => 'Fund', 'source' => ExportSource::FUND_CODE, 'width' => 6, 'align' => 'left'],
            ['sort_order' => 6, 'label' => 'DrCr', 'source' => ExportSource::DR_OR_CR, 'width' => 2, 'align' => 'left'],
            ['sort_order' => 7, 'label' => 'Amount', 'source' => ExportSource::ABSOLUTE_AMOUNT_CENTS, 'format_mask' => 'money:int|pad-zero:12', 'width' => 12, 'align' => 'right', 'pad_char' => '0'],
            ['sort_order' => 8, 'label' => 'Description', 'source' => ExportSource::DESCRIPTION, 'width' => 60, 'align' => 'left'],
        ];

        $this->syncColumns($template, $columns);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    protected function syncColumns(ExportTemplate $template, array $columns): void
    {
        foreach ($columns as $col) {
            ExportTemplateColumn::query()->updateOrCreate(
                ['export_template_id' => $template->id, 'sort_order' => $col['sort_order']],
                $col,
            );
        }
    }
}
