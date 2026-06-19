<?php

use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
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
    $this->exhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
    ]);
    $this->order = ExhibitorOrder::factory()->create([
        'exhibitor_id' => $this->exhibitor->id,
    ]);
});

it('renders the exhibitor detail page', function () {
    $response = $this->actingAs($this->user)
        ->get("/exhibitors/{$this->exhibitor->id}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('exhibitors/show')
            ->where('exhibitor.id', $this->exhibitor->id)
            ->has('exhibitor.orders')
            ->has('exhibitor.totals')
        );
});

it('renders the exhibitor event detail page', function () {
    $response = $this->actingAs($this->user)
        ->get("/exhibitor-events/{$this->exhibitor->exhibitor_event_id}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('exhibitors/event-show')
            ->has('event.exhibitors')
        );
});

it('renders the order detail page with catalog', function () {
    $response = $this->actingAs($this->user)
        ->get("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}");

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('exhibitors/order-show')
            ->where('order.id', $this->order->id)
            ->has('order.items')
            ->has('catalog')
        );
});

it('blocks viewing an order that does not belong to the exhibitor', function () {
    $otherExhibitor = Exhibitor::factory()->create([
        'exhibitor_event_id' => $this->exhibitor->exhibitor_event_id,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/exhibitors/{$otherExhibitor->id}/orders/{$this->order->id}");

    $response->assertNotFound();
});

it('adds a catalog item to an order and recalculates totals', function () {
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();

    $response = $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/items", [
            'equipment_item_id' => $item->id,
            'quantity' => 5,
        ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();

    $this->order->refresh();
    expect($this->order->items)->toHaveCount(1)
        ->and($this->order->items->first()->sku)->toBe('CHAIR-FOLD')
        ->and($this->order->items->first()->quantity)->toBe(5)
        ->and($this->order->items->first()->line_total_cents)->toBe(5 * 800)
        ->and($this->order->subtotal_cents)->toBe(5 * 800)
        ->and($this->order->total_cents)->toBe((int) round((5 * 800) * 1.07));
});

it('removes an order item and recalculates totals', function () {
    $item = EquipmentItem::query()->where('sku', 'TABLE-6FT')->firstOrFail();
    $line = ExhibitorOrderItem::fromCatalog($this->order, $item, 3);
    $this->order->recalculateTotals();
    expect($this->order->refresh()->subtotal_cents)->toBe(3 * 2200);

    $response = $this->actingAs($this->user)
        ->delete("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/items/{$line->id}");

    $response->assertRedirect();
    expect($this->order->refresh()->items)->toHaveCount(0)
        ->and($this->order->refresh()->subtotal_cents)->toBe(0)
        ->and($this->order->refresh()->total_cents)->toBe(0);
});

it('rejects adding an item with an invalid equipment_item_id', function () {
    $response = $this->actingAs($this->user)
        ->post("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}/items", [
            'equipment_item_id' => 999999,
            'quantity' => 1,
        ]);

    $response->assertSessionHasErrors(['equipment_item_id']);
});

it('snapshots sku/name/price onto the order line', function () {
    $item = EquipmentItem::query()->where('sku', 'BOOTH-10X10')->firstOrFail();
    $originalPrice = $item->unit_price_cents;

    $line = ExhibitorOrderItem::fromCatalog($this->order, $item, 1);

    $item->update(['unit_price_cents' => 99_999, 'name' => 'Renamed Booth']);

    $line->refresh();
    expect($line->unit_price_cents)->toBe($originalPrice)
        ->and($line->name)->toBe('10x10 pipe-and-drape booth');
});

it('requires auth for all exhibitor detail endpoints', function () {
    $this->get("/exhibitors/{$this->exhibitor->id}")->assertRedirect('/login');
    $this->get("/exhibitor-events/{$this->exhibitor->exhibitor_event_id}")->assertRedirect('/login');
    $this->get("/exhibitors/{$this->exhibitor->id}/orders/{$this->order->id}")->assertRedirect('/login');
});
