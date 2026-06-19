<?php

use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DemoJournalSeeder;
use Database\Seeders\FundsSeeder;
use Database\Seeders\SentinelBayVenuesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('seeds a balanced general ledger', function () {
    $this->seed(DemoJournalSeeder::class);

    expect(JournalEntry::count())->toBeGreaterThan(0);
    expect((int) JournalEntry::sum('debit_cents'))
        ->toBe((int) JournalEntry::sum('credit_cents'));
});

it('includes reversible manual entries', function () {
    $this->seed(DemoJournalSeeder::class);

    expect(JournalEntry::whereNotNull('entry_group')->count())->toBeGreaterThan(0);
});

it('is append-only safe on re-run', function () {
    $this->seed(DemoJournalSeeder::class);
    $before = JournalEntry::count();

    $this->seed(DemoJournalSeeder::class);

    expect(JournalEntry::count())->toBe($before);
});
