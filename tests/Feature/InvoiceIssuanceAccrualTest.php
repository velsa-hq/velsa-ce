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
    $this->service = app(InvoiceService::class);

    $client = Client::factory()->create();
    $this->booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 10_000_00,
        'deposit_percent' => 100,
    ]);
});

/** Net (debit - credit) on an account for an invoice's journal entries. */
function netOn(Invoice $invoice, string $code): int
{
    $q = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('account_code', $code);

    return (int) (clone $q)->sum('debit_cents') - (int) (clone $q)->sum('credit_cents');
}

it('posts the accrual at issuance - debit A/R, credit revenue', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($invoice->revenue_posted_at)->not->toBeNull();
    expect(netOn($invoice, '1100'))->toBe(10_000_00);   // A/R debited
    expect(netOn($invoice, '4100'))->toBe(-10_000_00);  // revenue credited
});

it('does not double-post revenue on idempotent re-issuance', function () {
    $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->issueDepositForBooking($this->booking->fresh());

    $invoice = Invoice::query()->firstOrFail();
    expect(netOn($invoice, '1100'))->toBe(10_000_00);
});

it('clears A/R to zero across the full issue -> pay lifecycle', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->applyPaymentToInvoice($invoice->fresh(), 10_000_00, 'check');

    expect(netOn($invoice, '1100'))->toBe(0);            // A/R settled
    expect(netOn($invoice, '1010'))->toBe(10_000_00);    // cash in
    expect(netOn($invoice, '4100'))->toBe(-10_000_00);   // revenue recognized
});

it('reverses the issuance accrual on void', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $this->service->void($invoice->fresh(), 'Issued in error');

    expect(netOn($invoice, '1100'))->toBe(0);  // A/R debit + reversal credit net out
    expect(netOn($invoice, '4100'))->toBe(0);  // revenue recognized then reversed
});

it('backfills issuance for a legacy invoice, including the tax leg', function () {
    $invoice = Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $this->booking->id,
        'status' => InvoiceStatus::Issued->value,
        'subtotal_cents' => 10_000,
        'tax_cents' => 800,
        'total_cents' => 10_800,
        'paid_cents' => 0,
        'revenue_posted_at' => null,
    ]);

    $this->artisan('accounting:backfill-issuance')->assertSuccessful();

    $invoice->refresh();
    expect($invoice->revenue_posted_at)->not->toBeNull();
    expect(netOn($invoice, '1100'))->toBe(10_800);   // A/R = total
    expect(netOn($invoice, '4100'))->toBe(-10_000);  // revenue = subtotal
    expect(netOn($invoice, '2200'))->toBe(-800);     // sales tax payable
});

it('dry-run backfill posts nothing', function () {
    $invoice = Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $this->booking->id,
        'status' => InvoiceStatus::Issued->value,
        'subtotal_cents' => 10_000,
        'tax_cents' => 0,
        'total_cents' => 10_000,
        'paid_cents' => 0,
        'revenue_posted_at' => null,
    ]);

    $this->artisan('accounting:backfill-issuance --dry-run')->assertSuccessful();

    expect($invoice->fresh()->revenue_posted_at)->toBeNull();
    expect(JournalEntry::count())->toBe(0);
});

it('backfill skips draft and void invoices', function () {
    $draft = Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $this->booking->id,
        'status' => InvoiceStatus::Draft->value,
        'total_cents' => 5_000,
        'subtotal_cents' => 5_000,
        'revenue_posted_at' => null,
    ]);
    $void = Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $this->booking->id,
        'status' => InvoiceStatus::Void->value,
        'total_cents' => 5_000,
        'subtotal_cents' => 5_000,
        'revenue_posted_at' => null,
    ]);

    $this->artisan('accounting:backfill-issuance')->assertSuccessful();

    expect($draft->fresh()->revenue_posted_at)->toBeNull();
    expect($void->fresh()->revenue_posted_at)->toBeNull();
});
