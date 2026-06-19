<?php

use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->venue = Venue::factory()->create();
});

it('renders the space detail page', function () {
    $space = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Grand Ballroom',
    ]);

    $this->actingAs($this->user)
        ->get("/spaces/{$space->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('spaces/show')
            ->where('space.id', $space->id)
            ->where('space.name', 'Grand Ballroom')
            ->where('venue.id', $this->venue->id)
            ->where('space.image_url', fn ($url) => str_contains((string) $url, "/identity/space-{$space->id}.svg"))
        );
});

it('includes sub-spaces and parent linkage', function () {
    $parent = Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'Hall']);
    $child = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'parent_space_id' => $parent->id,
        'name' => 'Hall · Section A',
    ]);

    $this->actingAs($this->user)
        ->get("/spaces/{$parent->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('spaces/show')
            ->where('stats.sub_space_count', 1)
            ->where('space.children.0.id', $child->id));

    $this->actingAs($this->user)
        ->get("/spaces/{$child->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('space.parent.id', $parent->id));
});
