<?php

use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->event = ExhibitorEvent::factory()->create();
});

it('creates an exhibitor from the admin side', function () {
    $this->actingAs($this->user)
        ->post('/exhibitors', [
            'exhibitor_event_id' => $this->event->id,
            'company_name' => 'Acme Displays',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '555-0100',
            'booth_assignment' => '101',
            'booth_size' => '10x20',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('exhibitors', [
        'company_name' => 'Acme Displays',
        'booth_assignment' => '101',
        'exhibitor_event_id' => $this->event->id,
    ]);
});

it('requires a company name and a valid event', function () {
    $this->actingAs($this->user)
        ->post('/exhibitors', ['company_name' => ''])
        ->assertSessionHasErrors(['company_name', 'exhibitor_event_id']);
});

it('updates an exhibitor', function () {
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create([
        'company_name' => 'Old Name',
        'booth_assignment' => '5',
    ]);

    $this->actingAs($this->user)
        ->patch("/exhibitors/{$exhibitor->id}", [
            'exhibitor_event_id' => $this->event->id,
            'company_name' => 'New Name',
            'booth_assignment' => '7',
        ])
        ->assertRedirect();

    expect($exhibitor->fresh())
        ->company_name->toBe('New Name')
        ->booth_assignment->toBe('7');
});

it('deletes an exhibitor with no recorded payments', function () {
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create();

    $this->actingAs($this->user)
        ->delete("/exhibitors/{$exhibitor->id}")
        ->assertRedirect('/exhibitors');

    $this->assertDatabaseMissing('exhibitors', ['id' => $exhibitor->id]);
});

it('blocks deleting an exhibitor that has recorded payments', function () {
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create();
    ExhibitorOrder::factory()->for($exhibitor)->create(['paid_cents' => 5000]);

    $this->actingAs($this->user)
        ->delete("/exhibitors/{$exhibitor->id}")
        ->assertSessionHas('toast.type', 'error');

    $this->assertDatabaseHas('exhibitors', ['id' => $exhibitor->id]);
});

it('surfaces work-order completion on the exhibitor detail', function () {
    $exhibitor = Exhibitor::factory()->for($this->event, 'event')->create();
    WorkOrder::factory()->create(['exhibitor_id' => $exhibitor->id, 'status' => 'completed']);
    WorkOrder::factory()->create(['exhibitor_id' => $exhibitor->id, 'status' => 'open']);

    $this->actingAs($this->user)
        ->get("/exhibitors/{$exhibitor->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exhibitors/show')
            ->where('exhibitor.work_order_summary.total', 2)
            ->where('exhibitor.work_order_summary.completed', 1));
});

it('requires authentication', function () {
    $this->post('/exhibitors', [])->assertRedirect('/login');
});
