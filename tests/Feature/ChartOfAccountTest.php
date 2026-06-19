<?php

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('derives normal_balance from account_type on save when missing', function () {
    $asset = ChartOfAccount::factory()->ofType(AccountType::Asset)->create([
        'code' => '1999', 'normal_balance' => '',
    ]);
    expect($asset->refresh()->normal_balance)->toBe('debit');

    $liab = ChartOfAccount::factory()->ofType(AccountType::Liability)->create([
        'code' => '2999', 'normal_balance' => '',
    ]);
    expect($liab->refresh()->normal_balance)->toBe('credit');
});

it('uses code as the route key', function () {
    $account = ChartOfAccount::query()->where('code', '1010')->firstOrFail();
    expect($account->getRouteKeyName())->toBe('code');
});

it('treats accounts as active when active_from/to are null', function () {
    $account = ChartOfAccount::query()->where('code', '1010')->firstOrFail();
    expect($account->isActive())->toBeTrue();
});

it('treats retired accounts as inactive', function () {
    $account = ChartOfAccount::factory()->retired()->create();
    expect($account->isActive())->toBeFalse();
});

it('seeds covers the runtime codes used by PostPaymentJournalEntries', function () {
    expect(ChartOfAccount::query()->where('code', '1010')->exists())->toBeTrue()
        ->and(ChartOfAccount::query()->where('code', '4200')->exists())->toBeTrue();
});

it('rejects posting a journal entry against a non-postable parent account', function () {
    JournalEntry::post([
        'account_code' => '1000', // non-postable header row
        'description' => 'X',
        'debit_cents' => 100_00,
    ]);
})->throws(RuntimeException::class, 'roll-up');

it('rejects posting a journal entry against a retired account', function () {
    $retired = ChartOfAccount::factory()->retired()->create(['code' => '9998']);

    JournalEntry::post([
        'account_code' => $retired->code,
        'description' => 'X',
        'debit_cents' => 100_00,
        'posted_on' => now()->toDateString(),
    ]);
})->throws(RuntimeException::class, 'not active');

it('rejects posting against an unknown account code', function () {
    JournalEntry::post([
        'account_code' => 'NOPE123',
        'description' => 'X',
        'debit_cents' => 100_00,
    ]);
})->throws(RuntimeException::class, 'does not exist in the chart of accounts');

it('populates chart_of_account_id and denormalizes account_code on save', function () {
    $entry = JournalEntry::post([
        'account_code' => '4200',
        'description' => 'Convention fee',
        'credit_cents' => 250_00,
    ]);

    $cash = ChartOfAccount::query()->where('code', '4200')->firstOrFail();
    expect($entry->chart_of_account_id)->toBe($cash->id)
        ->and($entry->account_code)->toBe('4200')
        ->and($entry->account?->code)->toBe('4200');
});

it('resolves account_code from chart_of_account_id when only the FK is provided', function () {
    $cash = ChartOfAccount::query()->where('code', '1010')->firstOrFail();
    $entry = JournalEntry::post([
        'chart_of_account_id' => $cash->id,
        'description' => 'Cash receipt',
        'debit_cents' => 100_00,
    ]);

    expect($entry->account_code)->toBe('1010');
});
