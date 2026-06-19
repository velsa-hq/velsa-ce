<?php

use App\Enums\FundType;
use App\Models\Fund;
use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('uses code as the route key', function () {
    $tourism = Fund::query()->where('code', 'TOURISM')->firstOrFail();
    expect($tourism->getRouteKeyName())->toBe('code');
});

it('seeds the three canonical funds', function () {
    expect(Fund::query()->where('code', 'GENERAL')->exists())->toBeTrue()
        ->and(Fund::query()->where('code', 'TOURISM')->exists())->toBeTrue()
        ->and(Fund::query()->where('code', 'ENTERPRISE')->exists())->toBeTrue();
});

it('tags TOURISM as a Special Revenue fund', function () {
    $tourism = Fund::query()->where('code', 'TOURISM')->firstOrFail();
    expect($tourism->fund_type)->toBe(FundType::SpecialRevenue);
});

it('treats retired funds as inactive', function () {
    $fund = Fund::factory()->retired()->create();
    expect($fund->isActive())->toBeFalse();
});

it('rejects posting a journal entry against an unknown fund code', function () {
    JournalEntry::post([
        'account_code' => '1010',
        'fund_code' => 'NOPE',
        'description' => 'X',
        'debit_cents' => 100_00,
    ]);
})->throws(RuntimeException::class, "Fund code 'NOPE' does not exist");

it('rejects posting a journal entry against a retired fund', function () {
    $fund = Fund::factory()->retired()->create(['code' => 'OLD']);

    JournalEntry::post([
        'account_code' => '1010',
        'fund_code' => $fund->code,
        'description' => 'X',
        'debit_cents' => 100_00,
        'posted_on' => now()->toDateString(),
    ]);
})->throws(RuntimeException::class, 'not active');

it('populates fund_id and denormalizes fund_code on save', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'fund_code' => 'TOURISM',
        'description' => 'Cash',
        'debit_cents' => 100_00,
    ]);

    $tourism = Fund::query()->where('code', 'TOURISM')->firstOrFail();
    expect($entry->fund_id)->toBe($tourism->id)
        ->and($entry->fund_code)->toBe('TOURISM')
        ->and($entry->fund?->code)->toBe('TOURISM');
});

it('allows fund to be omitted (system-level entries)', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'System entry - no fund',
        'debit_cents' => 100_00,
    ]);

    expect($entry->fund_id)->toBeNull()
        ->and($entry->fund_code)->toBeNull();
});
