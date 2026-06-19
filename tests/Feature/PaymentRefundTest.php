<?php

use App\Enums\ExhibitorOrderStatus;
use App\Models\AuditEvent;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use App\Services\Payments\OrderPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(EquipmentCatalogSeeder::class);

    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
        'email' => 'vendor@example.test',
    ]);
    $this->order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($this->order, $item, 10);
    $this->order->recalculateTotals();

    // issue an invoice + capture a full-balance payment
    app(InvoiceService::class)->issueForOrder($this->order->fresh());
    $this->payment = app(OrderPaymentService::class)->charge(
        $this->order->fresh(),
        'visa_tok_42424242',
        $this->order->fresh()->balanceCents(),
    );
});

it('refunds the full captured amount and walks the order back to pending', function () {
    $captured = $this->payment->amount_cents;

    $refunded = app(OrderPaymentService::class)->refund(
        $this->payment,
        $captured,
        reason: 'Cancelled by exhibitor',
    );

    expect($refunded->refunded_amount_cents)->toBe($captured)
        ->and($refunded->refunded_at)->not->toBeNull()
        ->and($refunded->effectiveAmountCents())->toBe(0)
        ->and($refunded->isFullyRefunded())->toBeTrue();

    $order = $this->order->fresh();
    expect($order->paid_cents)->toBe(0)
        ->and($order->status?->value)->toBe(ExhibitorOrderStatus::Pending->value);
});

it('issues a partial refund and leaves the rest captured', function () {
    $captured = $this->payment->amount_cents;
    $half = (int) floor($captured / 2);

    $refunded = app(OrderPaymentService::class)->refund(
        $this->payment,
        $half,
    );

    expect($refunded->refunded_amount_cents)->toBe($half)
        ->and($refunded->effectiveAmountCents())->toBe($captured - $half)
        ->and($refunded->isFullyRefunded())->toBeFalse();

    $order = $this->order->fresh();
    expect($order->paid_cents)->toBe($captured - $half)
        ->and($order->status?->value)->toBe(ExhibitorOrderStatus::PartiallyPaid->value);
});

it('clamps overshoot to the refundable amount', function () {
    $captured = $this->payment->amount_cents;

    $refunded = app(OrderPaymentService::class)->refund(
        $this->payment,
        $captured + 1_000_000,
    );

    expect($refunded->refunded_amount_cents)->toBe($captured);
});

it('refuses a refund on a non-captured payment', function () {
    $this->payment->update(['status' => 'failed']);

    app(OrderPaymentService::class)->refund($this->payment->fresh(), 100);
})->throws(RuntimeException::class, 'Only captured payments');

it('refuses a fully-refunded payment from being refunded again', function () {
    app(OrderPaymentService::class)->refund(
        $this->payment,
        $this->payment->amount_cents,
    );

    app(OrderPaymentService::class)->refund($this->payment->fresh(), 100);
})->throws(RuntimeException::class, 'no refundable amount remaining');

it('refuses a non-positive refund amount', function () {
    app(OrderPaymentService::class)->refund($this->payment, 0);
})->throws(RuntimeException::class, 'must be positive');

it('posts a balanced reversal journal pair (debit AR, credit Cash)', function () {
    $amount = 25_00; // partial refund - easier to isolate from capture entries

    app(OrderPaymentService::class)->refund($this->payment, $amount);

    $refundDebits = JournalEntry::query()
        ->where('source_type', ExhibitorPayment::class)
        ->where('source_id', $this->payment->id)
        ->where('account_code', '1100')
        ->sum('debit_cents');
    $refundCredits = JournalEntry::query()
        ->where('source_type', ExhibitorPayment::class)
        ->where('source_id', $this->payment->id)
        ->where('account_code', '1010')
        ->sum('credit_cents');

    expect((int) $refundDebits)->toBe($amount)
        ->and((int) $refundCredits)->toBe($amount);
});

it('writes a payment.refunded audit row', function () {
    app(OrderPaymentService::class)->refund(
        $this->payment,
        50_00,
        reason: 'Wrong size booth',
    );

    $audit = AuditEvent::query()
        ->where('event_type', 'payment.refunded')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['payment_id'])->toBe($this->payment->id)
        ->and($audit->payload_json['amount_cents'])->toBe(50_00)
        ->and($audit->payload_json['reason'])->toBe('Wrong size booth');
});

it('refreshes the invoice paid_cents + status when a refund decreases the order balance', function () {
    $invoice = Invoice::query()
        ->where('invoiceable_type', ExhibitorOrder::class)
        ->where('invoiceable_id', $this->order->id)
        ->firstOrFail();

    expect($invoice->fresh()->status?->value)->toBe('paid');

    app(OrderPaymentService::class)->refund(
        $this->payment,
        20_00,
    );

    $fresh = $invoice->fresh();
    expect($fresh->paid_cents)->toBe($this->order->fresh()->paid_cents)
        ->and($fresh->status?->value)->toBe('partial_paid');
});

it('records refunds against a manual payment (no processor round-trip)', function () {
    $order2 = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
    ]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($order2, $chair, 5);
    $order2->recalculateTotals();

    $manual = app(OrderPaymentService::class)->recordManual(
        $order2->fresh(),
        $order2->fresh()->balanceCents(),
        'check',
        reference: 'Check #1234',
    );

    $refunded = app(OrderPaymentService::class)->refund(
        $manual,
        10_00,
        reason: 'Overpayment',
    );

    expect($refunded->refunded_amount_cents)->toBe(10_00)
        ->and($refunded->provider)->toBe('manual');
});

it('exposes the refund endpoint via the admin controller', function () {
    $invoice = Invoice::query()
        ->where('invoiceable_type', ExhibitorOrder::class)
        ->where('invoiceable_id', $this->order->id)
        ->firstOrFail();
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post(
            "/admin/invoices/{$invoice->number}/payments/{$this->payment->id}/refund",
            ['amount_cents' => 30_00, 'reason' => 'Test']
        );

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($this->payment->fresh()->refunded_amount_cents)->toBe(30_00);
});

it('blocks refund on a payment that belongs to a different invoice', function () {
    $otherOrder = ExhibitorOrder::factory()->create();
    $otherInvoice = Invoice::factory()->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $otherOrder->id,
    ]);
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post(
            "/admin/invoices/{$otherInvoice->number}/payments/{$this->payment->id}/refund",
            ['amount_cents' => 100],
        );

    $response->assertNotFound();
});
