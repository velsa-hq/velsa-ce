<?php

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Reports\Handlers\BudgetVsActualReport;
use App\Reports\ReportRegistry;
use App\Services\Accounting\BudgetService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->year = FiscalYear::factory()->current()->create();
});

it('returns an empty result when no fiscal year is configured', function () {
    FiscalYear::query()->delete();

    $result = app(BudgetVsActualReport::class)->run([]);

    expect($result->rows)->toBeEmpty()
        ->and($result->description)->toContain('No fiscal year configured');
});

it('produces rows for a fiscal year with budgets and actuals', function () {
    $service = app(BudgetService::class);
    $account = ChartOfAccount::query()->where('code', '4200')->firstOrFail();

    $service->setBudget($this->year, $account, 100_000_00);
    JournalEntry::post([
        'account_code' => '4200',
        'credit_cents' => 75_000_00,
        'description' => 'Booking revenue',
        'posted_on' => $this->year->starts_on->addDays(5)->toDateString(),
    ]);

    $result = app(BudgetVsActualReport::class)->run([
        'fiscal_year_id' => $this->year->id,
    ]);

    $row = collect($result->rows)->firstWhere('account_code', '4200');

    expect($row)->not->toBeNull()
        ->and($row['budgeted'])->toBe('100,000.00')
        ->and($row['actual'])->toBe('75,000.00')
        ->and($row['used_pct'])->toBe('75%');
});

it('exposes year + fund options as parameters', function () {
    $report = app(BudgetVsActualReport::class);
    $params = $report->parameters();

    expect($params)->toHaveCount(2)
        ->and($params[0]['key'])->toBe('fiscal_year_id')
        ->and($params[1]['key'])->toBe('fund_id');
});

it('appears in the report registry', function () {
    $registry = app(ReportRegistry::class);

    expect($registry->has('budget-vs-actual'))->toBeTrue();
});
