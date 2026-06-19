<?php

use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin();
});

it('renders a balanced trial balance with per-account debit/credit balances', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 5000, 'posted_on' => '2026-06-01']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'test', 'credit_cents' => 5000, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->user)
        ->get('/accounting/trial-balance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/trial-balance')
            ->where('balanced', true)
            ->where('debit_total_cents', 5000)
            ->where('credit_total_cents', 5000)
            ->where('rows.0.account_code', '1010')
            ->where('rows.0.debit_balance_cents', 5000)
            ->where('rows.0.credit_balance_cents', 0)
            ->where('rows.1.account_code', '4200')
            ->where('rows.1.credit_balance_cents', 5000));
});

it('excludes entries posted after the as-of date', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 5000, 'posted_on' => '2026-06-01']);
    JournalEntry::post(['account_code' => '4200', 'description' => 'test', 'credit_cents' => 5000, 'posted_on' => '2026-06-01']);
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 9999, 'posted_on' => '2026-12-31']);

    $this->actingAs($this->user)
        ->get('/accounting/trial-balance?as_of=2026-06-30')
        ->assertInertia(fn (Assert $page) => $page
            ->where('debit_total_cents', 5000)   // dec entry excluded
            ->where('credit_total_cents', 5000));
});

it('computes a running balance on the account ledger', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 3000, 'posted_on' => '2026-06-01']);
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'credit_cents' => 1000, 'posted_on' => '2026-06-05']);

    $this->actingAs($this->user)
        ->get('/accounting/accounts/1010')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/account-ledger')
            ->where('account.code', '1010')
            ->where('opening_cents', 0)
            ->where('closing_cents', 2000)          // 3000 Dr - 1000 Cr
            ->where('entries.0.running_cents', 3000)
            ->where('entries.1.running_cents', 2000));
});

it('carries an opening balance forward when a from-date is set', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 3000, 'posted_on' => '2026-05-01']); // before window
    JournalEntry::post(['account_code' => '1010', 'description' => 'test', 'debit_cents' => 2000, 'posted_on' => '2026-06-10']); // in window

    $this->actingAs($this->user)
        ->get('/accounting/accounts/1010?from=2026-06-01')
        ->assertInertia(fn (Assert $page) => $page
            ->where('opening_cents', 3000)
            ->where('closing_cents', 5000)
            ->has('entries', 1));               // only the in-window entry
});

it('shows a credit balance as negative running for a revenue account', function () {
    JournalEntry::post(['account_code' => '4200', 'description' => 'test', 'credit_cents' => 7000, 'posted_on' => '2026-06-01']);

    $this->actingAs($this->user)
        ->get('/accounting/accounts/4200')
        ->assertInertia(fn (Assert $page) => $page
            ->where('closing_cents', -7000)         // net credit -> negative -> Cr
            ->where('entries.0.running_cents', -7000));
});
