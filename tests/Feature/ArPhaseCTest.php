<?php

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $this->client = Client::factory()->create();
    Contact::factory()->create([
        'client_id' => $this->client->id,
        'name' => 'Primary Contact',
        'email' => 'primary@client.test',
        'is_primary' => true,
    ]);
    $this->booking = Booking::factory()->create([
        'client_id' => $this->client->id,
        'total_cents' => 100_000_00, // $100,000
        'deposit_percent' => 50,
    ]);

    $this->service = app(InvoiceService::class);
});

// ---------- Booking invoicing ----------

it('issues a deposit invoice for the configured percent of booking total', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total_cents)->toBe(50_000_00)
        ->and($invoice->notes)->toBe('deposit')
        ->and($invoice->invoiceable_type)->toBe(Booking::class)
        ->and($invoice->invoiceable_id)->toBe($this->booking->id);
});

it('respects a custom deposit_percent', function () {
    $this->booking->update(['deposit_percent' => 25]);

    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($invoice->total_cents)->toBe(25_000_00);
});

it('is idempotent on deposit issuance', function () {
    $a = $this->service->issueDepositForBooking($this->booking->fresh());
    $b = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($b->id)->toBe($a->id)
        ->and(Invoice::query()->count())->toBe(1);
});

it('issues a balance invoice for the remainder after deposit', function () {
    $this->service->issueDepositForBooking($this->booking->fresh());

    $balance = $this->service->issueBalanceForBooking($this->booking->fresh());

    expect($balance->notes)->toBe('balance')
        ->and($balance->total_cents)->toBe(50_000_00)
        ->and($this->booking->fresh()->remainingToInvoiceCents())->toBe(0);
});

it('refuses balance issuance when no remaining amount exists', function () {
    $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->issueBalanceForBooking($this->booking->fresh());

    $this->service->issueBalanceForBooking($this->booking->fresh());
})->throws(RuntimeException::class, 'already fully invoiced');

it('refuses to invoice a booking with no total', function () {
    $empty = Booking::factory()->create(['total_cents' => 0]);

    $this->service->issueDepositForBooking($empty);
})->throws(RuntimeException::class, 'no total');

it('resolves email through Booking -> Client -> primary Contact', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($this->service->resolveInvoiceEmail($invoice))->toBe('primary@client.test');
});

it('falls back to any client contact email when no primary is flagged', function () {
    Contact::query()->where('client_id', $this->client->id)->delete();
    Contact::factory()->create([
        'client_id' => $this->client->id,
        'email' => 'secondary@client.test',
        'is_primary' => false,
    ]);
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    expect($this->service->resolveInvoiceEmail($invoice))->toBe('secondary@client.test');
});

// ---------- Manual payment on a booking invoice ----------

it('applies a manual payment to a booking invoice and marks it paid in full', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $applied = $this->service->applyPaymentToInvoice(
        $invoice->fresh(),
        $invoice->total_cents,
        'check',
        reference: 'Check #1234',
    );

    expect($applied->status)->toBe(InvoiceStatus::Paid)
        ->and($applied->paid_cents)->toBe($invoice->total_cents)
        ->and($applied->paid_at)->not->toBeNull();
});

it('applies a partial payment and leaves the invoice at PartialPaid', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $applied = $this->service->applyPaymentToInvoice(
        $invoice->fresh(),
        10_000_00,
        'wire',
    );

    expect($applied->status)->toBe(InvoiceStatus::PartialPaid)
        ->and($applied->paid_cents)->toBe(10_000_00);
});

it('clamps an overshoot to the remaining balance on a booking invoice', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $applied = $this->service->applyPaymentToInvoice(
        $invoice->fresh(),
        9_999_999_00,
        'cash',
    );

    expect($applied->paid_cents)->toBe($invoice->total_cents);
});

it('posts a balanced double-entry journal pair on manual payment (debit cash, credit AR)', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $this->service->applyPaymentToInvoice(
        $invoice->fresh(),
        50_000_00,
        'check',
        reference: 'Check #ABC',
    );

    $debits = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->sum('debit_cents');
    $credits = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->sum('credit_cents');

    expect((int) $debits)->toBe(100_000_00)
        ->and((int) $credits)->toBe(100_000_00);

    // payment clears A/R to zero
    $arNet = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('account_code', '1100')
        ->sum('debit_cents')
        - JournalEntry::query()
            ->where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->where('account_code', '1100')
            ->sum('credit_cents');
    expect((int) $arNet)->toBe(0);
});

it('refuses payment on a paid invoice', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->applyPaymentToInvoice($invoice->fresh(), $invoice->total_cents, 'check');

    $this->service->applyPaymentToInvoice($invoice->fresh(), 100, 'check');
})->throws(RuntimeException::class, 'Cannot apply payment to a paid invoice');

// ---------- Write-off ----------

it('writes off an unpaid invoice and posts bad-debt journal entries', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());

    $written = $this->service->writeOff($invoice->fresh(), 'Vendor bankrupt');

    expect($written->status)->toBe(InvoiceStatus::WrittenOff)
        ->and($written->void_reason)->toBe('Vendor bankrupt');

    $badDebtDebit = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('account_code', '5900')
        ->sum('debit_cents');
    $arCredit = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('account_code', '1100')
        ->sum('credit_cents');

    expect((int) $badDebtDebit)->toBe($invoice->total_cents)
        ->and((int) $arCredit)->toBe($invoice->total_cents);
});

it('writes off only the remaining balance on a partially paid invoice', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->applyPaymentToInvoice($invoice->fresh(), 20_000_00, 'wire');

    $written = $this->service->writeOff($invoice->fresh(), 'Client disputed');

    $remaining = 50_000_00 - 20_000_00;
    $badDebtDebit = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->where('account_code', '5900')
        ->sum('debit_cents');

    expect($written->status)->toBe(InvoiceStatus::WrittenOff)
        ->and((int) $badDebtDebit)->toBe($remaining);
});

it('refuses to write off a paid invoice', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->applyPaymentToInvoice($invoice->fresh(), $invoice->total_cents, 'check');

    $this->service->writeOff($invoice->fresh(), 'reason');
})->throws(RuntimeException::class, 'Cannot write off a paid invoice');

// ---------- Controller endpoints ----------

it('exposes booking deposit issuance via the admin endpoint', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post("/admin/bookings/{$this->booking->id}/invoices/deposit");

    $response->assertRedirect();
    expect($this->booking->fresh()->invoices)->toHaveCount(1);
});

it('exposes booking balance issuance via the admin endpoint', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin)
        ->post("/admin/bookings/{$this->booking->id}/invoices/deposit");
    $this->actingAs($admin)
        ->post("/admin/bookings/{$this->booking->id}/invoices/balance");

    expect($this->booking->fresh()->invoices)->toHaveCount(2);
});

it('exposes write-off via the admin endpoint', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post("/admin/invoices/{$invoice->number}/write-off", [
            'reason' => 'Uncollectable',
        ]);

    $response->assertRedirect();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::WrittenOff);
});

it('routes booking-invoice manual payments through InvoiceService', function () {
    $invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post("/admin/invoices/{$invoice->number}/payments", [
            'amount_cents' => $invoice->total_cents,
            'method' => 'check',
            'reference' => 'Check #999',
        ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});
