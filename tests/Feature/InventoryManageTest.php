<?php

use App\Models\InventoryKind;
use App\Models\ResourceInventory;
use App\Models\Venue;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->venue = Venue::factory()->create();
    InventoryKind::factory()->system()->create(['key' => 'chairs', 'label' => 'Chairs']);
    InventoryKind::factory()->system()->create(['key' => 'av', 'label' => 'A/V']);
});

it('lists inventory and filters by venue', function () {
    $other = Venue::factory()->create();
    $mine = ResourceInventory::factory()->create(['venue_id' => $this->venue->id]);
    ResourceInventory::factory()->create(['venue_id' => $other->id]);

    $this->actingAs($this->user)
        ->get("/inventory?venue_id={$this->venue->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/index')
            ->where('resources', fn ($rows) => collect($rows)->pluck('id')->all() === [$mine->id])
            ->has('venues'));
});

it('adds a resource', function () {
    $this->actingAs($this->user)
        ->post('/inventory', [
            'venue_id' => $this->venue->id,
            'name' => 'Stacking chairs',
            'kind' => 'chairs',
            'sku' => 'CHR-STD',
            'quantity_total' => 500,
            'quantity_available' => 460,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(ResourceInventory::query()->where('sku', 'CHR-STD')->first())
        ->not->toBeNull()
        ->quantity_total->toBe(500)
        ->quantity_available->toBe(460);
});

it('rejects available greater than total', function () {
    $this->actingAs($this->user)
        ->post('/inventory', [
            'venue_id' => $this->venue->id,
            'name' => 'Over',
            'kind' => 'av',
            'quantity_total' => 10,
            'quantity_available' => 20,
        ])
        ->assertSessionHasErrors('quantity_available');
});

it('reports low-stock resources via the reorder point', function () {
    $low = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'is_consumable' => true,
        'quantity_total' => 10,
        'quantity_available' => 2,
        'reorder_point' => 5,
    ]);
    ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'is_consumable' => true,
        'quantity_total' => 10,
        'quantity_available' => 9,
        'reorder_point' => 5,
    ]);
    // a durable below its reorder point must not flag low
    ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'is_consumable' => false,
        'quantity_total' => 10,
        'quantity_available' => 1,
        'reorder_point' => 5,
    ]);

    $this->actingAs($this->user)
        ->get('/inventory?low_only=1')
        ->assertInertia(fn (Assert $page) => $page
            ->where('low_count', 1)
            ->where('resources', fn ($rows) => collect($rows)->pluck('id')->all() === [$low->id]));
});

it('rejects a kind that is not in the taxonomy', function () {
    $this->actingAs($this->user)
        ->post('/inventory', [
            'venue_id' => $this->venue->id,
            'name' => 'Mystery',
            'kind' => 'not-a-real-kind',
            'quantity_total' => 1,
            'quantity_available' => 1,
        ])
        ->assertSessionHasErrors('kind');
});

it('enforces a unique SKU per venue', function () {
    ResourceInventory::factory()->create(['venue_id' => $this->venue->id, 'sku' => 'DUP']);

    $this->actingAs($this->user)
        ->post('/inventory', [
            'venue_id' => $this->venue->id,
            'name' => 'Dup',
            'kind' => 'av',
            'sku' => 'DUP',
            'quantity_total' => 1,
            'quantity_available' => 1,
        ])
        ->assertSessionHasErrors('sku');
});

it('updates a resource', function () {
    $r = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'quantity_total' => 10,
        'quantity_available' => 10,
    ]);

    $this->actingAs($this->user)
        ->patch("/inventory/{$r->id}", [
            'venue_id' => $this->venue->id,
            'name' => 'Renamed',
            'kind' => 'chairs',
            'quantity_total' => 20,
            'quantity_available' => 15,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($r->fresh())->name->toBe('Renamed')->quantity_total->toBe(20);
});

it('filters by consumable vs durable', function () {
    $c = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'is_consumable' => true,
    ]);
    $d = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'is_consumable' => false,
    ]);

    $this->actingAs($this->user)
        ->get('/inventory?type=consumable')
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources', fn ($rows) => collect($rows)->pluck('id')->all() === [$c->id]));

    $this->actingAs($this->user)
        ->get('/inventory?type=durable')
        ->assertInertia(fn (Assert $page) => $page
            ->where('resources', fn ($rows) => collect($rows)->pluck('id')->all() === [$d->id]));
});

it('prints an inventory count sheet', function () {
    Pdf::fake();
    ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
    ]);

    $this->actingAs($this->user)->get('/inventory/print')->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->viewName)->toBe('pdf.inventory-sheet')
            ->and($pdf->viewData['rows'])->toHaveCount(1);

        return true;
    });
});

it('lists applied movements on the use-activity report', function () {
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'kind' => 'chairs',
        'name' => 'Chairs',
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);
    $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Chairs',
        'quantity' => 5,
        'action' => 'deploy',
        'applied_at' => now(),
    ]);
    // unapplied item must not appear
    $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Tables',
        'quantity' => 2,
        'action' => 'deploy',
    ]);

    $this->actingAs($this->user)
        ->get('/inventory/activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/activity')
            ->has('rows', 1)
            ->where('rows.0.resource_name', 'Chairs'));
});

it('retires (soft-deletes) a resource', function () {
    $r = ResourceInventory::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->delete("/inventory/{$r->id}")
        ->assertRedirect();

    expect(ResourceInventory::query()->find($r->id))->toBeNull()
        ->and(ResourceInventory::withTrashed()->find($r->id))->not->toBeNull();
});
