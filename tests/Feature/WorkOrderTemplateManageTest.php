<?php

use App\Models\User;
use App\Models\Venue;
use App\Models\WorkOrderTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
    $this->venue = Venue::factory()->create();
});

it('renders the recurring-template admin', function () {
    WorkOrderTemplate::factory()->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->get('/admin/work-order-templates')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/work-order-templates/index')
            ->has('templates')
            ->has('venues')
            ->has('kinds')
            ->has('actions'));
});

it('creates a recurring template, composing an RRULE', function () {
    $this->actingAs($this->user)
        ->post('/admin/work-order-templates', [
            'venue_id' => $this->venue->id,
            'name' => 'Biweekly filter swap',
            'kind' => 'preventive_maintenance',
            'frequency' => 'weekly',
            'interval' => 2,
            'weekday' => 'WE',
            'hour' => 9,
            'lookahead_days' => 21,
            'default_assignee_role' => 'ops_lead',
            'is_active' => true,
            'items' => [
                ['name' => 'HVAC filter', 'quantity' => 2, 'unit' => 'each', 'action' => 'consume'],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $t = WorkOrderTemplate::query()->where('name', 'Biweekly filter swap')->first();
    expect($t)->not->toBeNull()
        ->and($t->recurrence_rrule)->toContain('FREQ=WEEKLY')
        ->and($t->recurrence_rrule)->toContain('INTERVAL=2')
        ->and($t->recurrence_rrule)->toContain('BYDAY=WE')
        ->and($t->recurrence_rrule)->toContain('BYHOUR=9')
        ->and($t->lookahead_days)->toBe(21)
        ->and($t->items_json)->toHaveCount(1)
        ->and($t->items_json[0]['name'])->toBe('HVAC filter');
});

it('composes a monthly RRULE', function () {
    $this->actingAs($this->user)
        ->post('/admin/work-order-templates', [
            'venue_id' => $this->venue->id,
            'name' => 'Monthly inspection',
            'kind' => 'preventive_maintenance',
            'frequency' => 'monthly',
            'interval' => 1,
            'monthday' => 15,
            'hour' => 8,
            'lookahead_days' => 30,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $t = WorkOrderTemplate::query()->where('name', 'Monthly inspection')->first();
    expect($t->recurrence_rrule)->toContain('FREQ=MONTHLY')
        ->and($t->recurrence_rrule)->toContain('BYMONTHDAY=15');
});

it('updates + deletes a template', function () {
    $t = WorkOrderTemplate::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Old',
        'recurrence_rrule' => 'FREQ=WEEKLY;INTERVAL=1;BYDAY=MO;BYHOUR=8;BYMINUTE=0',
    ]);

    $this->actingAs($this->user)
        ->put("/admin/work-order-templates/{$t->id}", [
            'venue_id' => $this->venue->id,
            'name' => 'Renamed',
            'kind' => 'cleaning',
            'frequency' => 'weekly',
            'interval' => 1,
            'weekday' => 'FR',
            'hour' => 7,
            'lookahead_days' => 14,
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
    expect($t->fresh())->name->toBe('Renamed')->is_active->toBeFalse();

    $this->actingAs($this->user)
        ->delete("/admin/work-order-templates/{$t->id}")
        ->assertRedirect();
    expect(WorkOrderTemplate::query()->find($t->id))->toBeNull();
});
