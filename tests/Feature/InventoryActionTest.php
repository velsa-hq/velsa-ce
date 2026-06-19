<?php

use App\Enums\InventoryAction;
use App\Models\ResourceInventory;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('Deploy decreases available without touching total', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $venue->id]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 30,
        'action' => InventoryAction::Deploy->value,
        'applied_at' => null,
    ]);

    $item->applyToInventory();

    expect($resource->fresh()->quantity_available)->toBe(70)
        ->and($resource->fresh()->quantity_total)->toBe(100)
        ->and($item->fresh()->applied_at)->not->toBeNull();
});

it('Return increases available back', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 70,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => WorkOrder::factory()->create(['venue_id' => $venue->id])->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 30,
        'action' => InventoryAction::Return->value,
    ]);

    $item->applyToInventory();

    expect($resource->fresh()->quantity_available)->toBe(100);
});

it('Consume decreases both available and total', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => WorkOrder::factory()->create(['venue_id' => $venue->id])->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 5,
        'action' => InventoryAction::Consume->value,
    ]);

    $item->applyToInventory();

    expect($resource->fresh()->quantity_available)->toBe(95)
        ->and($resource->fresh()->quantity_total)->toBe(95);
});

it('Replace leaves quantities unchanged (net-zero swap)', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => WorkOrder::factory()->create(['venue_id' => $venue->id])->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 5,
        'action' => InventoryAction::Replace->value,
    ]);

    $item->applyToInventory();

    expect($resource->fresh()->quantity_available)->toBe(100)
        ->and($resource->fresh()->quantity_total)->toBe(100);
});

it('applyToInventory is idempotent (no double-deduction)', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => WorkOrder::factory()->create(['venue_id' => $venue->id])->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 10,
        'action' => InventoryAction::Consume->value,
    ]);

    $item->applyToInventory();
    $item->applyToInventory(); // no-op

    expect($resource->fresh()->quantity_available)->toBe(90)
        ->and($resource->fresh()->quantity_total)->toBe(90);
});

it('clamps available to zero when deploying more than is on hand', function () {
    $venue = Venue::factory()->create();
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'quantity_total' => 100,
        'quantity_available' => 5,
    ]);
    $item = WorkOrderItem::factory()->create([
        'work_order_id' => WorkOrder::factory()->create(['venue_id' => $venue->id])->id,
        'resource_inventory_id' => $resource->id,
        'quantity' => 10,
        'action' => InventoryAction::Deploy->value,
    ]);

    $item->applyToInventory();

    expect($resource->fresh()->quantity_available)->toBe(0);
});
