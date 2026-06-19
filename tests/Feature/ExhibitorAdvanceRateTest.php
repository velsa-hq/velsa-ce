<?php

use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderForEvent(ExhibitorEvent $event): ExhibitorOrder
{
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);

    return ExhibitorOrder::factory()->create(['exhibitor_id' => $exhibitor->id]);
}

it('charges the advance (base) rate before the deadline', function () {
    $event = ExhibitorEvent::factory()->create([
        'advance_rate_deadline' => now()->addWeek(),
        'late_order_surcharge_pct' => 20,
    ]);
    $item = EquipmentItem::factory()->create(['unit_price_cents' => 10_000]);

    $line = ExhibitorOrderItem::fromCatalog(orderForEvent($event), $item, 1);

    expect($line->unit_price_cents)->toBe(10_000);
});

it('applies the late surcharge after the deadline', function () {
    $event = ExhibitorEvent::factory()->create([
        'advance_rate_deadline' => now()->subDay(),
        'late_order_surcharge_pct' => 20,
    ]);
    $item = EquipmentItem::factory()->create(['unit_price_cents' => 10_000]);

    $line = ExhibitorOrderItem::fromCatalog(orderForEvent($event), $item, 1);

    expect($line->unit_price_cents)->toBe(12_000); // +20%
});

it('charges the base rate when no surcharge is configured', function () {
    $event = ExhibitorEvent::factory()->create([
        'advance_rate_deadline' => now()->subDay(),
        'late_order_surcharge_pct' => 0,
    ]);
    $item = EquipmentItem::factory()->create(['unit_price_cents' => 10_000]);

    $line = ExhibitorOrderItem::fromCatalog(orderForEvent($event), $item, 1);

    expect($line->unit_price_cents)->toBe(10_000);
});

it('pricedNowCents + lateRateActive reflect the deadline', function () {
    $past = ExhibitorEvent::factory()->create(['advance_rate_deadline' => now()->subDay(), 'late_order_surcharge_pct' => 10]);
    $future = ExhibitorEvent::factory()->create(['advance_rate_deadline' => now()->addDay(), 'late_order_surcharge_pct' => 10]);

    expect($past->lateRateActive())->toBeTrue()
        ->and($past->pricedNowCents(5_000))->toBe(5_500)
        ->and($future->lateRateActive())->toBeFalse()
        ->and($future->pricedNowCents(5_000))->toBe(5_000);
});
