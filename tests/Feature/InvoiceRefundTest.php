<?php

use App\Enums\InvoiceStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\ExhibitorOrder;
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

    $client = Client::factory()->create();
    Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
        'email' => 'primary@client.test',
    ]);
    $this->booking = Booking::factory()->create([
        'client_id' => $client->id,
        'total_cents' => 100_000_00,
        'deposit_percent' => 50,
    ]);

    $this->service = app(InvoiceService::class);

    // deposit invoice, paid in full
    $this->invoice = $this->service->issueDepositForBooking($this->booking->fresh());
    $this->service->applyPaymentToInvoice($this->invoice, $this->invoice->total_cents, 'check');
    $this->invoice = $this->invoice->fresh();
});

it('refunds the full paid amount and walks the invoice back to issued', function () {
    $refunded = $this->service->refundInvoice($this->invoice, $this->invoice->paid_cents, 'Booking cancelled');

    expect($refunded->paid_cents)->toBe(0)
        ->and($refunded->status)->toBe(InvoiceStatus::Issued)
        ->and($refunded->paid_at)->toBeNull();
});

it('issues a partial refund and walks the invoice to partial_paid', function () {
    $captured = $this->invoice->paid_cents;
    $half = (int) floor($captured / 2);

    $refunded = $this->service->refundInvoice($this->invoice, $half);

    expect($refunded->paid_cents)->toBe($captured - $half)
        ->and($refunded->status)->toBe(InvoiceStatus::PartialPaid);
});

it('clamps overshoot to the paid amount', function () {
    $captured = $this->invoice->paid_cents;

    $refunded = $this->service->refundInvoice($this->invoice, $captured + 1_000_000);

    expect($refunded->paid_cents)->toBe(0);
});

it('refuses a refund on an unpaid (Issued) invoice', function () {
    $unpaid = $this->service->issueDepositForBooking(
        Booking::factory()->create([
            'client_id' => $this->booking->client_id,
            'total_cents' => 50_000_00,
            'deposit_percent' => 50,
        ]),
    );

    $this->service->refundInvoice($unpaid, 100);
})->throws(RuntimeException::class, 'no payment to refund');

it('refuses a refund on a void invoice', function () {
    $this->invoice->update(['status' => InvoiceStatus::Void->value]);
    $this->service->refundInvoice($this->invoice->fresh(), 100);
})->throws(RuntimeException::class, 'Cannot refund a void');

it('refuses a refund on a written-off invoice', function () {
    $this->invoice->update(['status' => InvoiceStatus::WrittenOff->value]);
    $this->service->refundInvoice($this->invoice->fresh(), 100);
})->throws(RuntimeException::class, 'Cannot refund a written_off');

it('refuses a non-positive refund amount', function () {
    $this->service->refundInvoice($this->invoice, 0);
})->throws(RuntimeException::class, 'must be positive');

it('posts a balanced reversal journal pair (debit AR 1100, credit Cash 1010)', function () {
    $amount = 25_00;

    $this->service->refundInvoice($this->invoice, $amount, 'Partial refund');

    // filter by description so the issuance accrual and original payment don't bleed in
    $refundDebits = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $this->invoice->id)
        ->where('account_code', '1100')
        ->where('description', 'like', '%efund%')
        ->sum('debit_cents');
    $refundCredits = JournalEntry::query()
        ->where('source_type', Invoice::class)
        ->where('source_id', $this->invoice->id)
        ->where('account_code', '1010')
        ->where('description', 'like', '%efund%')
        ->sum('credit_cents');

    expect((int) $refundDebits)->toBe($amount)
        ->and((int) $refundCredits)->toBe($amount);
});

it('writes an invoice.refund_applied audit row', function () {
    $this->service->refundInvoice($this->invoice, 100_00, 'Wrong amount captured');

    $audit = AuditEvent::query()
        ->where('event_type', 'invoice.refund_applied')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['amount_cents'])->toBe(100_00)
        ->and($audit->payload_json['reason'])->toBe('Wrong amount captured');
});

it('exposes the refund endpoint for booking invoices', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post(
            "/admin/invoices/{$this->invoice->number}/refund",
            ['amount_cents' => 30_00, 'reason' => 'Booking date changed'],
        );

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($this->invoice->fresh()->paid_cents)
        ->toBe($this->invoice->paid_cents - 30_00);
});

it('refuses the refund endpoint on exhibitor-order invoices', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $exhibitorOrder = ExhibitorOrder::factory()->create();
    $invoice = Invoice::factory()->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $exhibitorOrder->id,
        'paid_cents' => 10_000,
        'total_cents' => 10_000,
        'status' => InvoiceStatus::Paid->value,
    ]);

    $response = $this->actingAs($admin)
        ->post("/admin/invoices/{$invoice->number}/refund", ['amount_cents' => 100]);

    // redirects back; paid_cents stays untouched
    $response->assertRedirect();
    expect($invoice->fresh()->paid_cents)->toBe(10_000);
});
