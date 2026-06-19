<?php

use App\Enums\InvoiceStatus;
use App\Mail\DunningNotice;
use App\Mail\PaymentReceipt;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Accounting\InvoiceService;
use App\Services\Payments\OrderPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\EquipmentCatalogSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

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
    ]);
    $chair = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    ExhibitorOrderItem::fromCatalog($this->order, $chair, 10);
    $this->order->recalculateTotals();
});

// ---------- Dunning email ----------

it('sends a DunningNotice when an invoice transitions to first_notice', function () {
    Mail::fake();
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $this->order->id,
    ]);

    app(InvoiceService::class)->advanceDunning($invoice);

    Mail::assertSent(DunningNotice::class, fn (DunningNotice $m) => $m->hasTo($this->exhibitor->email)
        && $m->stage->value === 'first_notice'
    );
});

it('does not resend the dunning email when stage is unchanged', function () {
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $this->order->id,
    ]);
    app(InvoiceService::class)->advanceDunning($invoice);

    Mail::fake();
    app(InvoiceService::class)->advanceDunning($invoice->fresh());

    Mail::assertNothingSent();
});

it('escalates the subject line by stage', function () {
    Mail::fake();
    $invoice = Invoice::factory()->pastDue(daysOverdue: 75)->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $this->order->id,
    ]);

    app(InvoiceService::class)->advanceDunning($invoice);

    Mail::assertSent(DunningNotice::class, fn (DunningNotice $m) => str_contains($m->envelope()->subject, 'URGENT')
    );
});

it('skips the dunning email when exhibitor has no email on file', function () {
    Mail::fake();
    $this->exhibitor->update(['email' => '']);
    $invoice = Invoice::factory()->pastDue(daysOverdue: 3)->create([
        'invoiceable_type' => ExhibitorOrder::class,
        'invoiceable_id' => $this->order->id,
    ]);

    app(InvoiceService::class)->advanceDunning($invoice);

    Mail::assertNothingSent();
});

// ---------- Payment receipt email ----------

it('sends a PaymentReceipt after a successful card capture', function () {
    Mail::fake();

    app(OrderPaymentService::class)->charge(
        $this->order->fresh(),
        'visa_tok_42424242',
        $this->order->fresh()->balanceCents(),
    );

    Mail::assertSent(PaymentReceipt::class, fn (PaymentReceipt $m) => $m->hasTo($this->exhibitor->email));
});

it('does not send a receipt on a declined payment', function () {
    Mail::fake();

    app(OrderPaymentService::class)->charge(
        $this->order->fresh(),
        'visa_tok_0000',
        1000,
    );

    Mail::assertNotSent(PaymentReceipt::class);
});

// ---------- Manual payment recording ----------

it('records a manual payment, dispatches PaymentCaptured, and emails a receipt', function () {
    Mail::fake();
    $balance = $this->order->fresh()->balanceCents();

    $payment = app(OrderPaymentService::class)->recordManual(
        order: $this->order->fresh(),
        amountCents: $balance,
        method: 'check',
        reference: 'Check #4502',
    );

    expect($payment->provider)->toBe('manual')
        ->and($payment->card_brand)->toBe('check')
        ->and($payment->provider_transaction_id)->toBe('Check #4502')
        ->and($this->order->fresh()->paid_cents)->toBe($balance);

    Mail::assertSent(PaymentReceipt::class);
});

it('clamps a manual payment to the remaining balance', function () {
    $balance = $this->order->fresh()->balanceCents();

    $payment = app(OrderPaymentService::class)->recordManual(
        order: $this->order->fresh(),
        amountCents: $balance + 100_00,
        method: 'wire',
    );

    expect($payment->amount_cents)->toBe($balance);
});

it('refuses a non-positive manual payment', function () {
    app(OrderPaymentService::class)->recordManual(
        order: $this->order->fresh(),
        amountCents: 0,
        method: 'cash',
    );
})->throws(RuntimeException::class, 'must be positive');

it('records a manual payment via the admin controller endpoint', function () {
    $invoice = app(InvoiceService::class)->issueForOrder($this->order->fresh());
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post("/admin/invoices/{$invoice->number}/payments", [
            'amount_cents' => 1000,
            'method' => 'check',
            'reference' => 'Check #999',
        ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect($this->order->fresh()->payments->where('provider', 'manual'))->toHaveCount(1);
});

it('rejects a manual payment with an unknown method', function () {
    $invoice = app(InvoiceService::class)->issueForOrder($this->order->fresh());
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)
        ->post("/admin/invoices/{$invoice->number}/payments", [
            'amount_cents' => 1000,
            'method' => 'cryptocurrency',
        ]);

    $response->assertSessionHasErrors(['method']);
});

it('keeps the invoice in lockstep with the manual payment via the refresh listener', function () {
    $invoice = app(InvoiceService::class)->issueForOrder($this->order->fresh());
    expect($invoice->status)->toBe(InvoiceStatus::Issued);

    $balance = $this->order->fresh()->balanceCents();
    app(OrderPaymentService::class)->recordManual(
        order: $this->order->fresh(),
        amountCents: $balance,
        method: 'wire',
    );

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->paid_cents)->toBe($balance);
});
