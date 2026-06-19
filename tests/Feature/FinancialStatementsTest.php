<?php

use App\Models\JournalEntry;
use App\Services\Accounting\FinancialStatementService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $today = now()->toDateString();
    // $1,000 revenue (cash in)
    JournalEntry::post(['account_code' => '1010', 'description' => 'cash in', 'debit_cents' => 100_000, 'posted_on' => $today]);
    JournalEntry::post(['account_code' => '4100', 'description' => 'revenue', 'credit_cents' => 100_000, 'posted_on' => $today]);
    // $300 utilities
    JournalEntry::post(['account_code' => '5200', 'description' => 'utilities', 'debit_cents' => 30_000, 'posted_on' => $today]);
    JournalEntry::post(['account_code' => '1010', 'description' => 'pay utilities', 'credit_cents' => 30_000, 'posted_on' => $today]);
});

it('computes a balanced balance sheet with net income rolled into equity', function () {
    $bs = app(FinancialStatementService::class)->balanceSheet(now());

    expect($bs['assets_total_cents'])->toBe(70_000)        // cash 100k - 30k
        ->and($bs['liabilities_total_cents'])->toBe(0)
        ->and($bs['current_earnings_cents'])->toBe(70_000) // rev 100k - exp 30k
        ->and($bs['equity_total_cents'])->toBe(70_000)
        ->and($bs['balanced'])->toBeTrue()
        ->and($bs['assets_total_cents'])->toBe($bs['liabilities_total_cents'] + $bs['equity_total_cents']);
});

it('computes the income statement net income', function () {
    $is = app(FinancialStatementService::class)->incomeStatement(now()->startOfYear(), now());

    expect($is['revenue_total_cents'])->toBe(100_000)
        ->and($is['expense_total_cents'])->toBe(30_000)
        ->and($is['net_income_cents'])->toBe(70_000);
});

it('excludes activity outside the income-statement window', function () {
    $is = app(FinancialStatementService::class)->incomeStatement(
        now()->subYears(2)->startOfYear(),
        now()->subYears(2)->endOfYear(),
    );

    expect($is['revenue_total_cents'])->toBe(0)
        ->and($is['net_income_cents'])->toBe(0);
});

it('serves the statement report pages', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->get('/reports/balance-sheet')->assertOk();
    $this->actingAs($admin)->get('/reports/income-statement')->assertOk();
});

it('exports the balance sheet to CSV', function () {
    $admin = grantSuperAdmin();

    $response = $this->actingAs($admin)->get('/reports/balance-sheet/export.csv');
    $response->assertOk();

    expect($response->streamedContent())->toContain('Total assets');
});
