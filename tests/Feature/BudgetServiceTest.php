<?php

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Fund;
use App\Models\JournalEntry;
use App\Services\Accounting\BudgetService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $this->year = FiscalYear::factory()->current()->create();
    $this->service = app(BudgetService::class);

    $this->revenueAcct = ChartOfAccount::query()->where('code', '4200')->firstOrFail();
    $this->expenseAcct = ChartOfAccount::query()->where('code', '5400')->firstOrFail();
    $this->tourismFund = Fund::query()->where('code', 'TOURISM')->firstOrFail();
});

it('sets a budget line and stores audit metadata', function () {
    $budget = $this->service->setBudget($this->year, $this->revenueAcct, 500_000_00);

    expect($budget->amount_cents)->toBe(500_000_00)
        ->and($budget->chart_of_account_id)->toBe($this->revenueAcct->id)
        ->and($budget->fiscal_year_id)->toBe($this->year->id);
});

it('upserts on the (year, account, fund) unique key', function () {
    $a = $this->service->setBudget($this->year, $this->revenueAcct, 100_000_00);
    $b = $this->service->setBudget($this->year, $this->revenueAcct, 250_000_00);

    expect($b->id)->toBe($a->id)
        ->and($b->amount_cents)->toBe(250_000_00)
        ->and(Budget::query()->count())->toBe(1);
});

it('refuses to budget a non-postable parent account', function () {
    $parent = ChartOfAccount::query()->where('code', '4000')->firstOrFail();
    $this->service->setBudget($this->year, $parent, 100_000_00);
})->throws(RuntimeException::class, 'roll-up');

it('refuses to budget when the fiscal year is closed', function () {
    $closed = FiscalYear::factory()->closed()->create();
    $this->service->setBudget($closed, $this->revenueAcct, 100_000_00);
})->throws(RuntimeException::class, 'closed');

it('computes variance for revenue accounts (actual > budget = favorable)', function () {
    $this->service->setBudget($this->year, $this->revenueAcct, 100_000_00);

    JournalEntry::post([
        'account_code' => '4200',
        'credit_cents' => 120_000_00,
        'description' => 'Revenue',
        'posted_on' => $this->year->starts_on->addDays(5)->toDateString(),
    ]);

    $summary = $this->service->summary($this->year);
    $row = collect($summary)->firstWhere('account_code', '4200');

    expect($row['budgeted_cents'])->toBe(100_000_00)
        ->and($row['actual_cents'])->toBe(120_000_00)
        ->and($row['variance_cents'])->toBe(20_000_00);
});

it('computes variance for expense accounts (actual > budget = unfavorable)', function () {
    $this->service->setBudget($this->year, $this->expenseAcct, 50_000_00);

    JournalEntry::post([
        'account_code' => '5400',
        'debit_cents' => 60_000_00,
        'description' => 'Supplies',
        'posted_on' => $this->year->starts_on->addDays(10)->toDateString(),
    ]);

    $summary = $this->service->summary($this->year);
    $row = collect($summary)->firstWhere('account_code', '5400');

    expect($row['budgeted_cents'])->toBe(50_000_00)
        ->and($row['actual_cents'])->toBe(60_000_00)
        ->and($row['variance_cents'])->toBe(-10_000_00);
});

it('flags unbudgeted accounts that have actuals', function () {
    JournalEntry::post([
        'account_code' => '5400',
        'debit_cents' => 1_000_00,
        'description' => 'Unplanned expense',
        'posted_on' => $this->year->starts_on->addDays(1)->toDateString(),
    ]);

    $summary = $this->service->summary($this->year);
    $row = collect($summary)->firstWhere('account_code', '5400');

    expect($row['is_unbudgeted'])->toBeTrue()
        ->and($row['budgeted_cents'])->toBe(0);
});

it('excludes journal entries outside the fiscal year window', function () {
    $this->service->setBudget($this->year, $this->expenseAcct, 50_000_00);
    JournalEntry::post([
        'account_code' => '5400',
        'debit_cents' => 100_00,
        'description' => 'Outside window',
        'posted_on' => $this->year->ends_on->addDays(1)->toDateString(),
    ]);

    $summary = $this->service->summary($this->year);
    $row = collect($summary)->firstWhere('account_code', '5400');

    expect($row['actual_cents'])->toBe(0);
});

it('rolls up totals by account type', function () {
    $this->service->setBudget($this->year, $this->revenueAcct, 100_000_00);
    $this->service->setBudget($this->year, $this->expenseAcct, 50_000_00);
    JournalEntry::post([
        'account_code' => '4200',
        'credit_cents' => 80_000_00,
        'description' => 'Rev',
        'posted_on' => $this->year->starts_on->addDays(5)->toDateString(),
    ]);
    JournalEntry::post([
        'account_code' => '5400',
        'debit_cents' => 40_000_00,
        'description' => 'Exp',
        'posted_on' => $this->year->starts_on->addDays(5)->toDateString(),
    ]);

    $totals = $this->service->totalsByAccountType($this->year);

    expect($totals['revenue']['budgeted_cents'])->toBe(100_000_00)
        ->and($totals['revenue']['actual_cents'])->toBe(80_000_00)
        ->and($totals['expense']['budgeted_cents'])->toBe(50_000_00)
        ->and($totals['expense']['actual_cents'])->toBe(40_000_00);
});

it('respects a fund filter when summarizing', function () {
    $this->service->setBudget(
        $this->year,
        $this->revenueAcct,
        100_000_00,
        fundId: $this->tourismFund->id,
    );

    $summary = $this->service->summary($this->year, fundId: $this->tourismFund->id);
    expect($summary)->toHaveCount(1);

    $generalFund = Fund::query()->where('code', 'GENERAL')->firstOrFail();
    $otherSummary = $this->service->summary($this->year, fundId: $generalFund->id);
    expect($otherSummary)->toBeEmpty();
});

it('closes and reopens a fiscal year', function () {
    $closed = $this->service->close($this->year);
    expect($closed->is_closed)->toBeTrue()
        ->and($closed->closed_at)->not->toBeNull();

    $reopened = $this->service->reopen($closed);
    expect($reopened->is_closed)->toBeFalse()
        ->and($reopened->closed_at)->toBeNull();
});
