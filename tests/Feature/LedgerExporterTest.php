<?php

use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Models\JournalEntry;
use App\Services\Accounting\ExportSource;
use App\Services\Accounting\FakeLedgerExporter;
use App\Services\Accounting\LedgerExporter;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\ExportTemplatesSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(ExportTemplatesSeeder::class);
});

it('binds FakeLedgerExporter as the LedgerExporter in the container', function () {
    expect(app(LedgerExporter::class))->toBeInstanceOf(FakeLedgerExporter::class);
});

it('claims unexported entries for the period into a new batch', function () {
    $period = now()->format('Y-m');
    JournalEntry::post(['account_code' => '1010', 'description' => 'Cash', 'debit_cents' => 100_00]);
    JournalEntry::post(['account_code' => '4200', 'description' => 'Revenue', 'credit_cents' => 100_00]);

    $batch = app(LedgerExporter::class)->exportPeriod($period);

    expect($batch->entry_count)->toBe(2)
        ->and($batch->debit_total_cents)->toBe(100_00)
        ->and($batch->credit_total_cents)->toBe(100_00)
        ->and($batch->status)->toBe('ready')
        ->and($batch->isBalanced())->toBeTrue()
        ->and($batch->export_template_id)->not->toBeNull()
        ->and(JournalEntry::query()->unexported()->count())->toBe(0);
});

it('marks the batch as unbalanced when debits dont equal credits', function () {
    $period = now()->format('Y-m');
    JournalEntry::post(['account_code' => '1010', 'description' => 'Cash', 'debit_cents' => 100_00]);
    JournalEntry::post(['account_code' => '4200', 'description' => 'Revenue', 'credit_cents' => 95_00]);

    $batch = app(LedgerExporter::class)->exportPeriod($period);

    expect($batch->status)->toBe('unbalanced')
        ->and($batch->isBalanced())->toBeFalse();
});

it('marks the batch as empty when no unexported entries fall in the period', function () {
    $batch = app(LedgerExporter::class)->exportPeriod('2020-01');

    expect($batch->status)->toBe('empty')
        ->and($batch->entry_count)->toBe(0);
});

it('renders the default CSV payload with a header and one row per entry', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'Cash receipt', 'debit_cents' => 100_00, 'fund_code' => 'TOURISM']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'Exhibitor revenue', 'credit_cents' => 100_00, 'fund_code' => 'TOURISM']);

    $batch = app(LedgerExporter::class)->exportPeriod(now()->format('Y-m'));
    $payload = app(LedgerExporter::class)->renderPayload($batch);

    expect($payload)->toStartWith('JournalNumber,LineNumber,Date,Account,Fund,Description,Debit,Credit')
        ->and($payload)->toContain('1010')
        ->and($payload)->toContain('4200')
        ->and($payload)->toContain('TOURISM')
        ->and($payload)->toContain('Cash receipt')
        ->and($payload)->toContain('100.00');
});

it('does not double-claim entries when exportPeriod is called twice', function () {
    $period = now()->format('Y-m');
    JournalEntry::post(['account_code' => '1010', 'description' => 'Cash', 'debit_cents' => 100_00]);

    $first = app(LedgerExporter::class)->exportPeriod($period);
    $second = app(LedgerExporter::class)->exportPeriod($period);

    expect($first->entry_count)->toBe(1)
        ->and($second->status)->toBe('empty')
        ->and($second->entry_count)->toBe(0);
});

it('exports using a non-default template when one is supplied', function () {
    $custom = ExportTemplate::factory()->csv()->create(['name' => 'Custom Two-Col', 'slug' => 'custom-two-col']);
    ExportTemplateColumn::factory()->create([
        'export_template_id' => $custom->id,
        'sort_order' => 1,
        'label' => 'Acct',
        'source' => ExportSource::ACCOUNT_CODE,
    ]);
    ExportTemplateColumn::factory()->create([
        'export_template_id' => $custom->id,
        'sort_order' => 2,
        'label' => 'Amt',
        'source' => ExportSource::DEBIT_CENTS,
        'format_mask' => 'money:dot',
    ]);

    JournalEntry::post(['account_code' => '4100', 'description' => 'Custom', 'debit_cents' => 50_00]);

    $batch = app(LedgerExporter::class)->exportPeriod(now()->format('Y-m'), null, $custom);
    $payload = app(LedgerExporter::class)->renderPayload($batch);

    expect($batch->export_template_id)->toBe($custom->id)
        ->and($payload)->toStartWith('Acct,Amt')
        ->and($payload)->toContain('4100,50.00');
});

it('renders a fixed-width payload when the template format is fixed_width', function () {
    $fw = ExportTemplate::factory()->fixedWidth()->create(['name' => 'FW Sample', 'slug' => 'fw-sample']);
    ExportTemplateColumn::factory()->create([
        'export_template_id' => $fw->id,
        'sort_order' => 1,
        'label' => 'Acct',
        'source' => ExportSource::ACCOUNT_CODE,
        'width' => 8,
        'align' => 'left',
    ]);
    ExportTemplateColumn::factory()->create([
        'export_template_id' => $fw->id,
        'sort_order' => 2,
        'label' => 'Amount',
        'source' => ExportSource::DEBIT_CENTS,
        'format_mask' => 'money:int',
        'width' => 10,
        'align' => 'right',
        'pad_char' => '0',
    ]);

    JournalEntry::post(['account_code' => '1010', 'description' => 'FW', 'debit_cents' => 12345]);

    $batch = app(LedgerExporter::class)->exportPeriod(now()->format('Y-m'), null, $fw);
    $payload = app(LedgerExporter::class)->renderPayload($batch);

    // header off in the factory's fixedWidth() state, so line 1 is the data row
    expect(rtrim($payload))->toBe('1010    0000012345');
});

it('re-renders historical batches via the template active at export time', function () {
    $template = ExportTemplate::query()->where('slug', 'ledger-generic-csv')->firstOrFail();

    JournalEntry::post(['account_code' => '1010', 'description' => 'Cash', 'debit_cents' => 100_00]);
    $batch = app(LedgerExporter::class)->exportPeriod(now()->format('Y-m'));

    expect($batch->export_template_id)->toBe($template->id);

    $payload = app(LedgerExporter::class)->renderPayload($batch);
    expect($payload)->toContain('JournalNumber');
});

it('throws when no template is configured and none is supplied', function () {
    ExportTemplate::query()->delete();
    JournalEntry::post(['account_code' => '1010', 'description' => 'X', 'debit_cents' => 100_00]);

    app(LedgerExporter::class)->exportPeriod(now()->format('Y-m'));
})->throws(RuntimeException::class, 'No export template configured');
