<?php

use App\Events\PaymentCaptured;
use App\Listeners\PostPaymentJournalEntries;
use App\Models\ExhibitorPayment;
use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('credits A/R (not revenue) on exhibitor payment capture', function () {
    $payment = ExhibitorPayment::factory()->create(['amount_cents' => 5000]);

    (new PostPaymentJournalEntries)->handle(new PaymentCaptured($payment));

    expect((int) JournalEntry::where('account_code', '1010')->sum('debit_cents'))->toBe(5000);
    expect((int) JournalEntry::where('account_code', '1100')->sum('credit_cents'))->toBe(5000);
    // revenue recognized at issuance, not on capture
    expect((int) JournalEntry::where('account_code', '4200')->sum('credit_cents'))->toBe(0);
});
