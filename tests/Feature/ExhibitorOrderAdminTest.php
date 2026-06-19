<?php

use App\Enums\ExhibitorOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
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

    $this->user = grantSuperAdmin();
    $event = ExhibitorEvent::factory()->create();
    $this->exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    $this->order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'paid_cents' => 0,
    ]);
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();
    $this->lineItem = ExhibitorOrderItem::fromCatalog($this->order, $item, 10);
    $this->order->recalculateTotals();
    app(InvoiceService::class)->issueForOrder($this->order->fresh());
});

it('captures a card payment from the admin order page', function () {
    $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/payments", [
            'card_token' => 'visa_tok_42424242',
        ])
        ->assertSessionHas('toast.type', 'success');

    expect($this->order->fresh()->paid_cents)->toBe($this->order->fresh()->total_cents);
    $this->assertDatabaseHas('exhibitor_payments', [
        'exhibitor_order_id' => $this->order->id,
        'status' => PaymentStatus::Captured->value,
    ]);
});

it('surfaces a declined card without recording a payment', function () {
    $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/payments", [
            'card_token' => 'visa_tok_00000000',
        ])
        ->assertSessionHas('toast.type', 'error');

    expect($this->order->fresh()->paid_cents)->toBe(0);
});

it('records a manual payment', function () {
    $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/payments/manual", [
            'amount_cents' => 5000,
            'method' => 'check',
            'reference' => 'CHK-1001',
        ])
        ->assertSessionHas('toast.type', 'success');

    expect($this->order->fresh()->paid_cents)->toBe(5000);
    $this->assertDatabaseHas('exhibitor_payments', [
        'exhibitor_order_id' => $this->order->id,
        'provider' => 'manual',
        'card_brand' => 'check',
    ]);
});

it('refunds a captured payment from the admin order page', function () {
    $payment = app(OrderPaymentService::class)->charge(
        $this->order->fresh(),
        'visa_tok_42424242',
        $this->order->fresh()->balanceCents(),
    );

    $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/payments/{$payment->id}/refund", [
            'amount_cents' => 2000,
            'reason' => 'Damaged goods',
        ])
        ->assertSessionHas('toast.type', 'success');

    expect($payment->fresh()->refunded_amount_cents)->toBe(2000);
});

it('blocks refunding a payment that belongs to another order', function () {
    $payment = app(OrderPaymentService::class)->charge(
        $this->order->fresh(),
        'visa_tok_42424242',
        1000,
    );
    $otherOrder = ExhibitorOrder::factory()->create(['exhibitor_id' => $this->exhibitor->id]);

    $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$otherOrder->id}/payments/{$payment->id}/refund", [
            'amount_cents' => 500,
        ])
        ->assertNotFound();
});

it('updates a line-item quantity and recalculates totals', function () {
    $before = $this->order->fresh()->total_cents;

    $this->actingAs($this->user)
        ->patch("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/items/{$this->lineItem->id}", [
            'quantity' => 20,
        ])
        ->assertSessionHas('toast.type', 'success');

    expect($this->lineItem->fresh()->quantity)->toBe(20)
        ->and($this->order->fresh()->total_cents)->toBeGreaterThan($before);
});

it('cancels an unpaid order and reopens it', function () {
    $this->actingAs($this->user)
        ->patch("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/status", ['status' => 'cancelled'])
        ->assertSessionHas('toast.type', 'success');
    expect($this->order->fresh()->status)->toBe(ExhibitorOrderStatus::Cancelled);

    $this->actingAs($this->user)
        ->patch("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/status", ['status' => 'pending'])
        ->assertSessionHas('toast.type', 'success');
    expect($this->order->fresh()->status)->toBe(ExhibitorOrderStatus::Pending);
});

it('refuses to cancel an order that has recorded payments', function () {
    app(OrderPaymentService::class)->charge($this->order->fresh(), 'visa_tok_42424242', 5000);

    $this->actingAs($this->user)
        ->patch("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/status", ['status' => 'cancelled'])
        ->assertSessionHas('toast.type', 'error');

    expect($this->order->fresh()->status)->not->toBe(ExhibitorOrderStatus::Cancelled);
});
