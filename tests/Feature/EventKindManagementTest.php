<?php

use App\Models\Booking;
use App\Models\EventKind;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists event kinds via the generic taxonomy admin', function () {
    EventKind::factory()->system()->create(['key' => 'wedding', 'label' => 'Wedding']);

    $this->actingAs($this->user)
        ->get('/admin/event-kinds')
        ->assertInertia(fn ($page) => $page
            ->component('admin/event-kinds/index')
            ->has('items', 1)
            ->where('items.0.label', 'Wedding')
            ->where('colors', []));
});

it('adds a custom event kind, deriving a slug key', function () {
    $this->actingAs($this->user)
        ->post('/admin/event-kinds', ['label' => 'Charity Gala'])
        ->assertRedirect();

    $this->assertDatabaseHas('event_kinds', [
        'key' => 'charity_gala',
        'label' => 'Charity Gala',
        'is_system' => false,
        'is_active' => true,
    ]);
});

it('rejects a duplicate key', function () {
    EventKind::factory()->create(['key' => 'expo', 'label' => 'Expo']);

    $this->actingAs($this->user)
        ->post('/admin/event-kinds', ['label' => 'Expo'])
        ->assertSessionHasErrors('label');
});

it('renames an event kind', function () {
    $kind = EventKind::factory()->create(['label' => 'Conf']);

    $this->actingAs($this->user)
        ->put("/admin/event-kinds/{$kind->id}", ['label' => 'Conference'])
        ->assertRedirect();

    expect($kind->fresh()->label)->toBe('Conference');
});

it('toggles + reorders', function () {
    $a = EventKind::factory()->create(['sort_order' => 0]);
    $b = EventKind::factory()->create(['sort_order' => 1]);

    $this->actingAs($this->user)->patch("/admin/event-kinds/{$a->id}/toggle")->assertRedirect();
    expect($a->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->user)->patch("/admin/event-kinds/{$b->id}/move", ['direction' => 'up'])->assertRedirect();
    expect($b->fresh()->sort_order)->toBe(0)
        ->and($a->fresh()->sort_order)->toBe(1);
});

it('protects system + in-use kinds, deletes unused custom ones', function () {
    $system = EventKind::factory()->system()->create();
    $this->actingAs($this->user)->delete("/admin/event-kinds/{$system->id}")->assertSessionHasErrors('event kind');
    expect(EventKind::query()->find($system->id))->not->toBeNull();

    $inUse = EventKind::factory()->create(['key' => 'gala']);
    Booking::factory()->create(['kind' => 'gala']);
    $this->actingAs($this->user)->delete("/admin/event-kinds/{$inUse->id}")->assertSessionHasErrors('event kind');
    expect(EventKind::query()->find($inUse->id))->not->toBeNull();

    $spare = EventKind::factory()->create(['key' => 'spare']);
    $this->actingAs($this->user)->delete("/admin/event-kinds/{$spare->id}")->assertRedirect();
    expect(EventKind::query()->find($spare->id))->toBeNull();
});
