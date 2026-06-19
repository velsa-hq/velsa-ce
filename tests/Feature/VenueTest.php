<?php

use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a slug from the name when none is provided', function () {
    $venue = Venue::factory()->create(['name' => 'Riverside-Lakeside Convention Center', 'slug' => null]);

    expect($venue->slug)->toBe('riverside-lakeside-convention-center');
});

it('disambiguates slug collisions with a numeric suffix', function () {
    Venue::factory()->create(['name' => 'The Northend', 'slug' => null]);
    $second = Venue::factory()->create(['name' => 'The Northend', 'slug' => null]);

    expect($second->slug)->toBe('the-northend-2');
});

it('honors an explicit slug when provided', function () {
    $venue = Venue::factory()->create(['name' => 'Whatever', 'slug' => 'custom-slug']);

    expect($venue->slug)->toBe('custom-slug');
});

it('treats null active_at as not-yet-active', function () {
    $venue = Venue::factory()->comingSoon()->create();

    expect($venue->isActive())->toBeFalse()
        ->and($venue->active_at)->toBeNull();
});

it('treats a past active_at and null retired_at as active', function () {
    $venue = Venue::factory()->create(['active_at' => now()->subYear(), 'retired_at' => null]);

    expect($venue->isActive())->toBeTrue();
});

it('treats a retired venue as not active', function () {
    $venue = Venue::factory()->retired()->create(['active_at' => now()->subYear()]);

    expect($venue->isActive())->toBeFalse();
});

it('scopes ->active() to venues whose active_at has elapsed', function () {
    Venue::factory()->create(['active_at' => now()->subDay()]);
    Venue::factory()->comingSoon()->create();
    Venue::factory()->create(['active_at' => now()->addDay()]);

    expect(Venue::query()->active()->count())->toBe(1);
});

it('scopes ->comingSoon() to venues with no active_at', function () {
    Venue::factory()->create(['active_at' => now()->subDay()]);
    Venue::factory()->comingSoon()->count(2)->create();

    expect(Venue::query()->comingSoon()->count())->toBe(2);
});

it('retires a venue via soft-delete (retired_at column)', function () {
    $venue = Venue::factory()->create();
    $venue->delete();

    expect(Venue::find($venue->id))->toBeNull()
        ->and($venue->trashed())->toBeTrue()
        ->and(Venue::withTrashed()->find($venue->id)->retired_at)->not->toBeNull();
});

it('exposes spaces relationship', function () {
    $venue = Venue::factory()->create();
    Space::factory()->count(3)->create(['venue_id' => $venue->id]);

    expect($venue->spaces()->count())->toBe(3);
});
