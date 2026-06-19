<?php

use App\Models\ResourceInventory;
use App\Models\Venue;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->venue = Venue::factory()->create();
});

it('renders the work-order detail page', function () {
    $wo = WorkOrder::factory()->create([
        'venue_id' => $this->venue->id,
        'title' => 'Fix the AC',
    ]);

    $this->actingAs($this->user)
        ->get("/work-orders/{$wo->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/show')
            ->where('work_order.id', $wo->id)
            ->where('work_order.title', 'Fix the AC')
            ->where('work_order.venue.id', $this->venue->id));
});

it('renders the create form with the venue preselected', function () {
    $this->actingAs($this->user)
        ->get("/work-orders/create?venue_id={$this->venue->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('work-orders/create')
            ->where('selected_venue_id', $this->venue->id)
            ->has('kinds')
            ->has('venues'));
});

it('creates a work order, assigning a reference + requester', function () {
    $this->actingAs($this->user)
        ->post('/work-orders', [
            'venue_id' => $this->venue->id,
            'title' => 'Replace HVAC filter',
            'kind' => 'preventive_maintenance',
            'priority' => 2,
        ])
        ->assertRedirect();

    $wo = WorkOrder::query()->where('title', 'Replace HVAC filter')->first();
    expect($wo)->not->toBeNull()
        ->and($wo->venue_id)->toBe($this->venue->id)
        ->and($wo->reference)->toStartWith('WO-')
        ->and($wo->requested_by_user_id)->toBe($this->user->id)
        ->and($wo->status->value)->toBe('open');
});

it('marks a work order assigned when an assignee is chosen', function () {
    $assignee = grantSuperAdmin();

    $this->actingAs($this->user)
        ->post('/work-orders', [
            'venue_id' => $this->venue->id,
            'title' => 'Stage the ballroom',
            'kind' => 'setup',
            'priority' => 3,
            'assigned_to_user_id' => $assignee->id,
        ])
        ->assertRedirect();

    $wo = WorkOrder::query()->where('title', 'Stage the ballroom')->first();
    expect($wo->status->value)->toBe('assigned')
        ->and($wo->assigned_to_user_id)->toBe($assignee->id);
});

it('windows completed/cancelled summaries to recent throughput', function () {
    WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'completed']);
    // >90d, excluded from summary
    $old = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'completed']);
    DB::table('work_orders')->where('id', $old->id)->update(['updated_at' => now()->subDays(200)]);
    // active stays all-time regardless of age
    $oldOpen = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'open']);
    DB::table('work_orders')->where('id', $oldOpen->id)->update(['updated_at' => now()->subDays(400)]);

    $this->actingAs($this->user)
        ->get('/work-orders')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary_window_days', 90)
            ->where('summary.completed.count', fn ($c) => (int) $c === 1)
            ->where('summary.open.count', fn ($c) => (int) $c === 1));
});

it('surfaces open work orders on the venue page', function () {
    $open = WorkOrder::factory()->create([
        'venue_id' => $this->venue->id,
        'title' => 'Open WO',
        'status' => 'open',
    ]);
    WorkOrder::factory()->create([
        'venue_id' => $this->venue->id,
        'title' => 'Done WO',
        'status' => 'completed',
    ]);

    $this->actingAs($this->user)
        ->get("/venues/{$this->venue->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('work_orders', fn ($rows) => collect($rows)->pluck('id')->all() === [$open->id]));
});

it('exposes editor data (venues/kinds/assignees) on the detail page', function () {
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->get("/work-orders/{$wo->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('venues')
            ->has('kinds')
            ->has('assignees')
            ->has('work_order.assigned_to_user_id'));
});

it('updates a work order', function () {
    $assignee = grantSuperAdmin();
    $wo = WorkOrder::factory()->create([
        'venue_id' => $this->venue->id,
        'title' => 'Old title',
        'priority' => 4,
    ]);

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}", [
            'venue_id' => $this->venue->id,
            'title' => 'New title',
            'kind' => 'repair',
            'priority' => 1,
            'assigned_to_user_id' => $assignee->id,
            'cost_cents' => 12500,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $wo->refresh();
    expect($wo->title)->toBe('New title')
        ->and($wo->priority)->toBe(1)
        ->and($wo->kind->value)->toBe('repair')
        ->and($wo->assigned_to_user_id)->toBe($assignee->id)
        ->and($wo->cost_cents)->toBe(12500);
});

it('moves a work order through its lifecycle', function () {
    $wo = WorkOrder::factory()->create([
        'venue_id' => $this->venue->id,
        'status' => 'open',
    ]);

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'in_progress'])
        ->assertRedirect();
    expect($wo->fresh()->status->value)->toBe('in_progress');

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();
    expect($wo->fresh())->status->value->toBe('completed')->completed_at->not->toBeNull();

    // reopening clears completed_at
    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'open'])
        ->assertRedirect();
    expect($wo->fresh())->status->value->toBe('open')->completed_at->toBeNull();
});

it('deletes a work order', function () {
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->delete("/work-orders/{$wo->id}")
        ->assertRedirect('/work-orders');

    expect(WorkOrder::query()->find($wo->id))->toBeNull();
});

it('prints a single work order to PDF', function () {
    Pdf::fake();
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)->get("/work-orders/{$wo->id}/print")->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->viewName)->toBe('pdf.work-orders')
            ->and($pdf->viewData['orders'])->toHaveCount(1);

        return true;
    });
});

it('prints only the selected ids when given', function () {
    Pdf::fake();
    $a = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);
    $b = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);
    WorkOrder::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->get("/work-orders/print?ids={$a->id},{$b->id}")
        ->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->viewData['orders'])->toHaveCount(2);

        return true;
    });
});

it('prints a filtered group of work orders to PDF', function () {
    Pdf::fake();
    WorkOrder::factory()->count(2)->create(['venue_id' => $this->venue->id, 'status' => 'open']);
    WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'completed']);

    $this->actingAs($this->user)->get('/work-orders/print?status=open')->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->viewName)->toBe('pdf.work-orders')
            ->and($pdf->viewData['orders'])->toHaveCount(2);

        return true;
    });
});

it('adds, updates, and removes work-order items', function () {
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'open']);

    $this->actingAs($this->user)
        ->post("/work-orders/{$wo->id}/items", [
            'name' => 'Folding chairs',
            'quantity' => 20,
            'unit' => 'each',
            'action' => 'deploy',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $item = $wo->items()->first();
    expect($item)->not->toBeNull()->name->toBe('Folding chairs')->quantity->toBe(20);

    $this->actingAs($this->user)
        ->patch("/work-order-items/{$item->id}", [
            'name' => 'Folding chairs',
            'quantity' => 30,
            'action' => 'deploy',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($item->fresh()->quantity)->toBe(30);

    $this->actingAs($this->user)
        ->delete("/work-order-items/{$item->id}")
        ->assertRedirect();
    expect($wo->items()->count())->toBe(0);
});

it('rejects an item resource from another venue', function () {
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id]);
    $foreign = ResourceInventory::factory()->create([
        'venue_id' => Venue::factory()->create()->id,
    ]);

    $this->actingAs($this->user)
        ->post("/work-orders/{$wo->id}/items", [
            'name' => 'Foreign',
            'quantity' => 1,
            'action' => 'deploy',
            'resource_inventory_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('resource_inventory_id');
});

it('applies inventory on completion and restores it on reopen', function () {
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'open']);
    $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Chairs',
        'quantity' => 30,
        'action' => 'deploy',
    ]);

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();
    expect($resource->fresh()->quantity_available)->toBe(70);

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'open'])
        ->assertRedirect();
    expect($resource->fresh()->quantity_available)->toBe(100);
});

it('returns stock when an applied work-order item is deleted', function () {
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'open']);
    $item = $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Chairs',
        'quantity' => 30,
        'action' => 'deploy',
    ]);

    $this->actingAs($this->user)->patch("/work-orders/{$wo->id}/status", ['status' => 'completed']);
    expect($resource->fresh()->quantity_available)->toBe(70);

    $this->actingAs($this->user)
        ->delete("/work-order-items/{$item->id}")
        ->assertRedirect();

    expect($resource->fresh()->quantity_available)->toBe(100);
});

it('adjusts inventory when an applied work-order item quantity changes', function () {
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'quantity_total' => 100,
        'quantity_available' => 100,
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'open']);
    $item = $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Chairs',
        'quantity' => 30,
        'action' => 'deploy',
    ]);

    $this->actingAs($this->user)->patch("/work-orders/{$wo->id}/status", ['status' => 'completed']);
    expect($resource->fresh()->quantity_available)->toBe(70);

    // reduce 30 -> 10: available rises 70 -> 90
    $this->actingAs($this->user)
        ->patch("/work-order-items/{$item->id}", [
            'resource_inventory_id' => $resource->id,
            'name' => 'Chairs',
            'quantity' => 10,
            'action' => 'deploy',
        ])
        ->assertRedirect();

    expect($resource->fresh()->quantity_available)->toBe(90);
});

it('consume reduces total, and deleting a completed order restores stock', function () {
    $resource = ResourceInventory::factory()->create([
        'venue_id' => $this->venue->id,
        'quantity_total' => 50,
        'quantity_available' => 50,
    ]);
    $wo = WorkOrder::factory()->create(['venue_id' => $this->venue->id, 'status' => 'in_progress']);
    $wo->items()->create([
        'resource_inventory_id' => $resource->id,
        'name' => 'Filters',
        'quantity' => 10,
        'action' => 'consume',
    ]);

    $this->actingAs($this->user)
        ->patch("/work-orders/{$wo->id}/status", ['status' => 'completed'])
        ->assertRedirect();
    expect($resource->fresh())->quantity_total->toBe(40)->quantity_available->toBe(40);

    $this->actingAs($this->user)
        ->delete("/work-orders/{$wo->id}")
        ->assertRedirect();
    expect($resource->fresh())->quantity_total->toBe(50)->quantity_available->toBe(50);
});
