<?php

use App\Models\Space;
use App\Models\SpaceKind;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists space kinds', function () {
    SpaceKind::factory()->system()->create(['key' => 'ballroom', 'label' => 'Ballroom']);

    $this->actingAs($this->user)
        ->get('/admin/space-kinds')
        ->assertInertia(fn ($page) => $page
            ->component('admin/space-kinds/index')
            ->has('items', 1));
});

it('adds a custom kind, deriving a slug key', function () {
    $this->actingAs($this->user)
        ->post('/admin/space-kinds', ['label' => 'Wedding Chapel'])
        ->assertRedirect();

    $this->assertDatabaseHas('space_kinds', [
        'key' => 'wedding_chapel',
        'label' => 'Wedding Chapel',
        'is_system' => false,
        'is_active' => true,
    ]);
});

it('rejects a kind whose slug already exists', function () {
    SpaceKind::factory()->create(['key' => 'chapel', 'label' => 'Chapel']);

    $this->actingAs($this->user)
        ->post('/admin/space-kinds', ['label' => 'Chapel'])
        ->assertSessionHasErrors('label');

    expect(SpaceKind::query()->where('key', 'chapel')->count())->toBe(1);
});

it('renames a kind without touching its key or visibility', function () {
    $kind = SpaceKind::factory()->create(['key' => 'lawn', 'label' => 'Lawn', 'is_active' => true]);

    $this->actingAs($this->user)
        ->put("/admin/space-kinds/{$kind->id}", ['label' => 'Garden Lawn'])
        ->assertRedirect();

    $fresh = $kind->fresh();
    expect($fresh->label)->toBe('Garden Lawn')
        ->and($fresh->is_active)->toBeTrue()
        ->and($fresh->key)->toBe('lawn');
});

it('hides and shows a kind via toggle', function () {
    $kind = SpaceKind::factory()->create(['key' => 'barn', 'is_active' => true]);

    $this->actingAs($this->user)
        ->patch("/admin/space-kinds/{$kind->id}/toggle")
        ->assertRedirect();
    expect($kind->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->user)
        ->patch("/admin/space-kinds/{$kind->id}/toggle")
        ->assertRedirect();
    expect($kind->fresh()->is_active)->toBeTrue();
});

it('reorders kinds by swapping sort_order with the neighbor', function () {
    $first = SpaceKind::factory()->create(['key' => 'a', 'sort_order' => 0]);
    $second = SpaceKind::factory()->create(['key' => 'b', 'sort_order' => 1]);

    $this->actingAs($this->user)
        ->patch("/admin/space-kinds/{$second->id}/move", ['direction' => 'up'])
        ->assertRedirect();

    expect($first->fresh()->sort_order)->toBe(1)
        ->and($second->fresh()->sort_order)->toBe(0);
});

it('refuses to delete a system kind', function () {
    $kind = SpaceKind::factory()->system()->create(['key' => 'arena', 'label' => 'Arena']);

    $this->actingAs($this->user)
        ->delete("/admin/space-kinds/{$kind->id}")
        ->assertSessionHasErrors('kind');

    expect(SpaceKind::query()->whereKey($kind->id)->exists())->toBeTrue();
});

it('refuses to delete a kind in use by a space', function () {
    $kind = SpaceKind::factory()->create(['key' => 'cabana', 'label' => 'Cabana']);
    $venue = Venue::factory()->create();
    Space::factory()->ofKind('cabana')->create(['venue_id' => $venue->id]);

    $this->actingAs($this->user)
        ->delete("/admin/space-kinds/{$kind->id}")
        ->assertSessionHasErrors('kind');

    expect(SpaceKind::query()->whereKey($kind->id)->exists())->toBeTrue();
});

it('deletes an unused custom kind', function () {
    $kind = SpaceKind::factory()->create(['key' => 'gazebo', 'label' => 'Gazebo']);

    $this->actingAs($this->user)
        ->delete("/admin/space-kinds/{$kind->id}")
        ->assertRedirect();

    expect(SpaceKind::query()->whereKey($kind->id)->exists())->toBeFalse();
});
