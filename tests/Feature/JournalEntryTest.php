<?php

use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('persists a journal entry via post() with sensible defaults', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'Cash receipt',
        'debit_cents' => 100_00,
    ]);

    expect($entry->posted_on->toDateString())->toBe(now()->toDateString())
        ->and($entry->debit_cents)->toBe(100_00)
        ->and($entry->credit_cents)->toBe(0);
});

it('rejects content updates after insert', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'Cash receipt',
        'debit_cents' => 100_00,
    ]);

    expect(fn () => $entry->update(['debit_cents' => 500_00]))
        ->toThrow(RuntimeException::class, 'immutable');
});

it('allows setting export_batch_id without tripping the immutability guard', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'Cash receipt',
        'debit_cents' => 100_00,
    ]);

    expect(fn () => $entry->update(['export_batch_id' => 42]))->not->toThrow(RuntimeException::class);
    expect($entry->fresh()->export_batch_id)->toBe(42);
});

it('refuses delete attempts via the model', function () {
    $entry = JournalEntry::post([
        'account_code' => '1010',
        'description' => 'Cash receipt',
        'debit_cents' => 100_00,
    ]);

    expect(fn () => $entry->delete())->toThrow(RuntimeException::class, 'append-only');
});

it('scopes ->unexported() to entries without an export_batch_id', function () {
    JournalEntry::post(['account_code' => '1010', 'description' => 'a', 'debit_cents' => 100_00]);
    JournalEntry::post(['account_code' => '1010', 'description' => 'b', 'debit_cents' => 200_00]);
    $marked = JournalEntry::post(['account_code' => '1010', 'description' => 'c', 'debit_cents' => 300_00]);
    $marked->update(['export_batch_id' => 1]);

    expect(JournalEntry::query()->unexported()->count())->toBe(2);
});
