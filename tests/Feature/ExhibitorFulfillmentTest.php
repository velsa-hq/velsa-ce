<?php

use App\Enums\ExhibitorOrderStatus;
use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\Booking;
use App\Models\Department;
use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\ResourceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Services\Exhibitors\ExhibitorFulfillmentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venue = Venue::factory()->create();
    $this->booking = Booking::factory()->create([
        'venue_id' => $this->venue->id,
        'start_at' => now()->addDays(10),
    ]);
    $this->event = ExhibitorEvent::factory()->create(['booking_id' => $this->booking->id]);
    $this->exhibitor = Exhibitor::factory()->for($this->event, 'event')->create([
        'booth_assignment' => '214',
    ]);

    $electrical = EquipmentCategory::factory()->create(['department' => 'electrical']);
    $furniture = EquipmentCategory::factory()->create(['department' => 'furniture']);
    $this->power = EquipmentItem::factory()->create(['equipment_category_id' => $electrical->id, 'sku' => 'POWER-20A']);
    $this->chair = EquipmentItem::factory()->create(['equipment_category_id' => $furniture->id, 'sku' => 'CHAIR-BQ']);
    $this->table = EquipmentItem::factory()->create(['equipment_category_id' => $furniture->id, 'sku' => 'TABLE-6']);
});

/** Placed order with the given [item, qty] lines. */
function placeOrder(Exhibitor $exhibitor, array $lines, string $status = 'pending'): ExhibitorOrder
{
    $order = ExhibitorOrder::factory()->for($exhibitor)->create([
        'status' => $status,
        'placed_at' => now(),
    ]);

    foreach ($lines as [$item, $qty]) {
        ExhibitorOrderItem::fromCatalog($order, $item, $qty);
    }
    $order->recalculateTotals(); // save -> observer reconciles

    return $order->fresh();
}

it('generates one work order per department when an order is confirmed', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1], [$this->chair, 2], [$this->table, 1]]);

    $wos = $order->workOrders()->with('items')->get();
    expect($wos)->toHaveCount(2);

    $electrical = $wos->firstWhere('department', 'electrical');
    $furniture = $wos->firstWhere('department', 'furniture');

    expect($electrical)->not->toBeNull()
        ->and($electrical->kind)->toBe(WorkOrderKind::Setup)
        ->and($electrical->exhibitor_id)->toBe($this->exhibitor->id)
        ->and($electrical->booking_id)->toBe($this->booking->id)
        ->and($electrical->venue_id)->toBe($this->venue->id)
        ->and($electrical->title)->toContain('Booth 214')
        ->and($electrical->items)->toHaveCount(1);

    expect($furniture->items)->toHaveCount(2);

    // generated items link back to their order line
    expect($furniture->items->pluck('exhibitor_order_item_id')->filter())->toHaveCount(2);
});

it('is idempotent - re-syncing does not duplicate work orders', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1], [$this->chair, 2]]);
    expect($order->workOrders()->count())->toBe(2);

    app(ExhibitorFulfillmentService::class)->syncForOrder($order);
    app(ExhibitorFulfillmentService::class)->syncForOrder($order);

    expect($order->workOrders()->count())->toBe(2);
});

it('reconciles a quantity change in place', function () {
    $order = placeOrder($this->exhibitor, [[$this->chair, 2]]);
    $line = $order->items()->where('sku', 'CHAIR-BQ')->first();
    $woId = $order->workOrders()->first()->id;

    $line->update(['quantity' => 9]);
    $order->recalculateTotals();

    expect($order->workOrders()->count())->toBe(1)
        ->and($order->workOrders()->first()->id)->toBe($woId)
        ->and($order->workOrders()->first()->items()->first()->quantity)->toBe(9);
});

it('cancels a department work order when its last line is removed', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1], [$this->chair, 2]]);
    expect($order->workOrders()->count())->toBe(2);

    $order->items()->where('sku', 'POWER-20A')->delete();
    $order->recalculateTotals();

    $electrical = $order->workOrders()->where('department', 'electrical')->first();
    $furniture = $order->workOrders()->where('department', 'furniture')->first();

    expect($electrical->status)->toBe(WorkOrderStatus::Cancelled)
        ->and($furniture->status)->toBe(WorkOrderStatus::Open);
});

it('cancels open work orders when the order is cancelled', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1], [$this->chair, 2]]);

    $order->update(['status' => ExhibitorOrderStatus::Cancelled->value]);

    expect($order->workOrders()->where('status', WorkOrderStatus::Cancelled->value)->count())->toBe(2);
});

it('does not generate until paid when the event trigger is "paid"', function () {
    $this->event->update(['settings_json' => ['work_order_trigger' => 'paid']]);

    $order = placeOrder($this->exhibitor->fresh(), [[$this->power, 1]]);
    expect($order->workOrders()->count())->toBe(0);

    $order->applyPayment($order->total_cents); // -> paid, save -> observer

    expect($order->fresh()->workOrders()->count())->toBe(1);
});

it('respects the generate_work_orders=false opt-out', function () {
    $this->event->update(['settings_json' => ['generate_work_orders' => false]]);

    $order = placeOrder($this->exhibitor->fresh(), [[$this->power, 1]]);

    expect($order->workOrders()->count())->toBe(0);
});

it('does not generate for an unconfirmed cart', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1]], status: 'cart');

    expect($order->workOrders()->count())->toBe(0);
});

it('links generated items to venue stock by SKU so completion moves inventory', function () {
    $stock = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'sku' => 'POWER-20A',
        'quantity_total' => 10,
        'quantity_available' => 10,
    ]);

    $order = placeOrder($this->exhibitor, [[$this->power, 3]]);
    $wo = $order->workOrders()->where('department', 'electrical')->first();

    expect($wo->items()->first()->resource_inventory_id)->toBe($stock->id);

    // complete through the real status route -> applyInventoryDeltas
    $this->actingAs(grantSuperAdmin())
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();

    // available -3, total unchanged
    expect($stock->fresh())
        ->quantity_available->toBe(7)
        ->and($stock->fresh()->quantity_total)->toBe(10);
});

it('cancels work orders when a paid-trigger order is refunded below paid', function () {
    $this->event->update(['settings_json' => ['work_order_trigger' => 'paid']]);
    $order = placeOrder($this->exhibitor->fresh(), [[$this->power, 1]]);
    $order->applyPayment($order->total_cents);
    expect($order->fresh()->workOrders()->where('status', '!=', 'cancelled')->count())->toBe(1);

    $order->reversePayment($order->total_cents); // back to pending

    expect($order->fresh()->workOrders()->where('status', '!=', 'cancelled')->count())->toBe(0);
});

it('locks a department for order edits once its setup is complete', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 2]]);
    $order->workOrders()->where('department', 'electrical')->first()
        ->update(['status' => WorkOrderStatus::Completed->value]);
    $line = $order->items()->where('sku', 'POWER-20A')->first();

    $this->actingAs(grantSuperAdmin())
        ->patch("/exhibitors/{$this->exhibitor->id}/orders/{$order->id}/items/{$line->id}", ['quantity' => 9])
        ->assertSessionHas('toast.type', 'error');

    expect($line->fresh()->quantity)->toBe(2);
});

it('preserves crew edits on generated items across an order change', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1]]);
    $woItem = $order->workOrders()->where('department', 'electrical')->first()->items()->first();
    $woItem->update(['notes' => 'Use the north panel']);

    $line = $order->items()->where('sku', 'POWER-20A')->first();
    $line->update(['quantity' => 4]);
    $order->recalculateTotals();

    expect($woItem->fresh())
        ->notes->toBe('Use the north panel') // crew edit survived
        ->quantity->toBe(4);                 // reconciled
});

it('reflects fulfillment completion on the exhibitor summary', function () {
    $order = placeOrder($this->exhibitor, [[$this->power, 1], [$this->chair, 2]]);
    $wo = $order->workOrders()->where('department', 'electrical')->first();
    $wo->update(['status' => WorkOrderStatus::Completed->value]);

    $this->actingAs(grantSuperAdmin())
        ->get("/exhibitors/{$this->exhibitor->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exhibitors/show')
            ->where('exhibitor.work_order_summary.total', 2)
            ->where('exhibitor.work_order_summary.completed', 1));
});

it('reverses applied inventory and removes work orders when its order is deleted', function () {
    $stock = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'sku' => 'POWER-20A',
        'quantity_total' => 10,
        'quantity_available' => 10,
    ]);

    $order = placeOrder($this->exhibitor, [[$this->power, 3]]);
    $wo = $order->workOrders()->where('department', 'electrical')->first();
    $this->actingAs(grantSuperAdmin())
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();
    expect($stock->fresh()->quantity_available)->toBe(7);

    $order->delete();

    // stock restored, derivative work order removed
    expect($stock->fresh()->quantity_available)->toBe(10);
    expect(WorkOrder::whereKey($wo->id)->exists())->toBeFalse();
});

it('blocks retiring a resource that still has inventory applied', function () {
    $stock = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'sku' => 'POWER-20A',
        'quantity_total' => 10,
        'quantity_available' => 10,
    ]);
    $order = placeOrder($this->exhibitor, [[$this->power, 2]]);
    $wo = $order->workOrders()->where('department', 'electrical')->first();
    $this->actingAs(grantSuperAdmin())
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();

    expect(fn () => $stock->delete())->toThrow(RuntimeException::class);
    expect($stock->fresh()->trashed())->toBeFalse();
});

it('auto-assigns a generated work order to the department default-role holder', function () {
    Department::query()->updateOrCreate(
        ['key' => 'electrical'],
        ['label' => 'Electrical', 'default_role' => 'ops_lead', 'sort_order' => 99],
    );
    $this->seed(RolesAndPermissionsSeeder::class);
    $lead = User::factory()->create();
    $lead->assignRoleAt($this->venue, 'ops_lead');

    $order = placeOrder($this->exhibitor, [[$this->power, 1]]);
    $wo = $order->workOrders()->where('department', 'electrical')->first();

    expect($wo->assigned_to_user_id)->toBe($lead->id);
});
