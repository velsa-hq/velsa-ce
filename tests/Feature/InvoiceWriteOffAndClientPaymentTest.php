<?php

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('writes off an open invoice via the route and posts the bad-debt pair', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 10000,
        'deposit_percent' => 100,
    ]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $this->actingAs(grantSuperAdmin())
        ->post("/admin/invoices/{$invoice->number}/write-off", ['reason' => 'uncollectable'])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::WrittenOff);
    expect(JournalEntry::where('account_code', '5900')->where('debit_cents', 10000)->exists())->toBeTrue();
    expect(JournalEntry::where('account_code', '1100')->where('credit_cents', 10000)->exists())->toBeTrue();
});

it('requires a reason to write off', function () {
    $client = Client::factory()->create();
    $booking = Booking::factory()->create(['client_id' => $client->id, 'total_cents' => 10000, 'deposit_percent' => 100]);
    $invoice = app(InvoiceService::class)->issueDepositForBooking($booking->fresh());

    $this->actingAs(grantSuperAdmin())
        ->post("/admin/invoices/{$invoice->number}/write-off", [])
        ->assertSessionHasErrors('reason');
});

it('records a manual payment on a client invoice', function () {
    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'invoiceable_type' => Client::class,
        'invoiceable_id' => $client->id,
        'status' => InvoiceStatus::Issued->value,
        'subtotal_cents' => 20000,
        'tax_cents' => 0,
        'total_cents' => 20000,
        'paid_cents' => 0,
    ]);

    $this->actingAs(grantSuperAdmin())
        ->post("/admin/invoices/{$invoice->number}/payments", [
            'amount_cents' => 20000,
            'method' => 'check',
            'reference' => 'Check #100',
        ])
        ->assertRedirect();

    $invoice->refresh();
    expect($invoice->paid_cents)->toBe(20000);
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
    expect(JournalEntry::where('account_code', '1010')->where('debit_cents', 20000)->exists())->toBeTrue();
    expect(JournalEntry::where('account_code', '1100')->where('credit_cents', 20000)->exists())->toBeTrue();
});
