<?php

use App\Enums\ExhibitorOrderStatus;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates an EX-prefixed order number on create', function () {
    $order = ExhibitorOrder::factory()->create(['order_number' => null]);

    expect($order->order_number)->toStartWith('EX-')
        ->and($order->order_number)->toMatch('/^EX-\d{4}-[A-Z0-9]{6}$/');
});

it('casts status to ExhibitorOrderStatus enum', function () {
    $order = ExhibitorOrder::factory()->create(['status' => ExhibitorOrderStatus::Paid->value]);

    expect($order->status)->toBe(ExhibitorOrderStatus::Paid);
});

it('auto-computes line_total_cents from quantity x unit_price_cents', function () {
    $order = ExhibitorOrder::factory()->create();
    $item = ExhibitorOrderItem::query()->create([
        'exhibitor_order_id' => $order->id,
        'name' => 'Booth',
        'quantity' => 3,
        'unit_price_cents' => 25_000,
        'line_total_cents' => 0, // recomputed on save
    ]);

    expect($item->line_total_cents)->toBe(75_000);
});

it('recalculateTotals sums items + 7% tax', function () {
    $order = ExhibitorOrder::factory()->create([
        'subtotal_cents' => 0,
        'tax_cents' => 0,
        'total_cents' => 0,
    ]);
    ExhibitorOrderItem::query()->create([
        'exhibitor_order_id' => $order->id,
        'name' => 'Booth',
        'quantity' => 1,
        'unit_price_cents' => 100_000,
        'line_total_cents' => 0,
    ]);
    ExhibitorOrderItem::query()->create([
        'exhibitor_order_id' => $order->id,
        'name' => 'Electricity',
        'quantity' => 1,
        'unit_price_cents' => 15_000,
        'line_total_cents' => 0,
    ]);

    $order->recalculateTotals();
    $fresh = $order->fresh();

    expect($fresh->subtotal_cents)->toBe(115_000)
        ->and($fresh->tax_cents)->toBe(8_050)
        ->and($fresh->total_cents)->toBe(123_050);
});

it('applyPayment partially marks status until total is reached', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_000,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    $order->applyPayment(40_000);
    expect($order->fresh()->status)->toBe(ExhibitorOrderStatus::PartiallyPaid)
        ->and($order->fresh()->paid_cents)->toBe(40_000);

    $order->applyPayment(60_000);
    expect($order->fresh()->status)->toBe(ExhibitorOrderStatus::Paid)
        ->and($order->fresh()->paid_cents)->toBe(100_000);
});

it('balanceCents reports remaining unpaid amount', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_000,
        'paid_cents' => 35_000,
    ]);

    expect($order->balanceCents())->toBe(65_000);
});

it('hides magic_token from JSON serialization', function () {
    $exhibitor = Exhibitor::factory()->create(['magic_token' => 'secret']);

    expect($exhibitor->toArray())->not->toHaveKey('magic_token');
});
