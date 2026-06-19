<?php

use App\Models\Space;
use App\Models\SpaceKind;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    SpaceKind::factory()->create(['key' => 'ballroom', 'label' => 'Ballroom', 'is_active' => true]);
    SpaceKind::factory()->inactive()->create(['key' => 'archived', 'label' => 'Archived']);
    $this->venue = Venue::factory()->create();
});

it('creates a space under a venue', function () {
    $this->actingAs($this->user)
        ->post("/venues/{$this->venue->slug}/spaces", [
            'name' => 'Grand Ballroom A',
            'kind' => 'ballroom',
            'capacity' => 400,
            'sqft' => 5000,
            'bookable_unit' => 'daily',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('spaces', [
        'venue_id' => $this->venue->id,
        'name' => 'Grand Ballroom A',
        'kind' => 'ballroom',
    ]);
});

it('rejects an unknown or inactive kind', function () {
    $this->actingAs($this->user)
        ->post("/venues/{$this->venue->slug}/spaces", [
            'name' => 'Bad Space',
            'kind' => 'nope',
            'bookable_unit' => 'daily',
        ])
        ->assertSessionHasErrors('kind');

    $this->actingAs($this->user)
        ->post("/venues/{$this->venue->slug}/spaces", [
            'name' => 'Inactive Kind Space',
            'kind' => 'archived',
            'bookable_unit' => 'daily',
        ])
        ->assertSessionHasErrors('kind');
});

it('updates a space', function () {
    $space = Space::factory()->ofKind('ballroom')->create(['venue_id' => $this->venue->id, 'name' => 'Old']);

    $this->actingAs($this->user)
        ->put("/spaces/{$space->id}", [
            'name' => 'New Name',
            'kind' => 'ballroom',
            'capacity' => 250,
            'bookable_unit' => 'hourly',
        ])
        ->assertRedirect();

    $fresh = $space->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->capacity)->toBe(250);
});

it('rejects a parent space from a different venue', function () {
    $otherVenue = Venue::factory()->create();
    $foreignParent = Space::factory()->ofKind('ballroom')->create(['venue_id' => $otherVenue->id]);

    $this->actingAs($this->user)
        ->post("/venues/{$this->venue->slug}/spaces", [
            'name' => 'Child',
            'kind' => 'ballroom',
            'bookable_unit' => 'daily',
            'parent_space_id' => $foreignParent->id,
        ])
        ->assertSessionHasErrors('parent_space_id');
});

it('refuses to set a space as its own parent', function () {
    $space = Space::factory()->ofKind('ballroom')->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->put("/spaces/{$space->id}", [
            'name' => $space->name,
            'kind' => 'ballroom',
            'bookable_unit' => 'daily',
            'parent_space_id' => $space->id,
        ])
        ->assertSessionHasErrors('parent_space_id');
});

it('retires (soft-deletes) a space', function () {
    $space = Space::factory()->ofKind('ballroom')->create(['venue_id' => $this->venue->id]);

    $this->actingAs($this->user)
        ->delete("/spaces/{$space->id}")
        ->assertRedirect();

    expect($space->fresh()->trashed())->toBeTrue();
});

it('refuses to retire a space that has sub-spaces', function () {
    $parent = Space::factory()->ofKind('ballroom')->create(['venue_id' => $this->venue->id]);
    Space::factory()->ofKind('ballroom')->childOf($parent)->create();

    $this->actingAs($this->user)
        ->delete("/spaces/{$parent->id}")
        ->assertSessionHasErrors('space');

    expect($parent->fresh()->trashed())->toBeFalse();
});
