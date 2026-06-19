<?php

use App\Enums\BookableUnit;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts kind and bookable_unit to enums', function () {
    $space = Space::factory()->create([
        'kind' => 'ballroom',
        'bookable_unit' => BookableUnit::Hourly->value,
    ]);

    expect($space->kind)->toBe('ballroom')
        ->and($space->bookable_unit)->toBe(BookableUnit::Hourly);
});

it('round-trips JSON attributes through the array cast', function () {
    $space = Space::factory()->create([
        'attributes_json' => ['hookup_type' => '50A', 'stallion_safe' => true, 'pad_count' => 40],
    ]);

    expect($space->fresh()->attributes_json)->toEqualCanonicalizing([
        'hookup_type' => '50A',
        'stallion_safe' => true,
        'pad_count' => 40,
    ]);
});

it('exposes venue, parent, and children relationships', function () {
    $venue = Venue::factory()->create();
    $parent = Space::factory()->create(['venue_id' => $venue->id]);
    $childA = Space::factory()->childOf($parent)->create();
    $childB = Space::factory()->childOf($parent)->create();

    expect($parent->venue->is($venue))->toBeTrue()
        ->and($childA->parent->is($parent))->toBeTrue()
        ->and($parent->children()->count())->toBe(2)
        ->and($parent->children->pluck('id')->all())->toContain($childA->id, $childB->id);
});

it('rejects self-parenting', function () {
    $space = Space::factory()->create();

    expect(fn () => $space->update(['parent_space_id' => $space->id]))
        ->toThrow(RuntimeException::class, 'own parent');
});

it('rejects creating a cycle through a chain of ancestors', function () {
    $venue = Venue::factory()->create();
    $a = Space::factory()->create(['venue_id' => $venue->id]);
    $b = Space::factory()->childOf($a)->create();
    $c = Space::factory()->childOf($b)->create();

    expect(fn () => $a->update(['parent_space_id' => $c->id]))
        ->toThrow(RuntimeException::class, 'cycle');
});

it('rejects a parent that belongs to a different venue', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    $parent = Space::factory()->create(['venue_id' => $venueA->id]);
    $child = Space::factory()->create(['venue_id' => $venueB->id]);

    expect(fn () => $child->update(['parent_space_id' => $parent->id]))
        ->toThrow(RuntimeException::class, 'same venue');
});

it('allows a valid parent within the same venue', function () {
    $venue = Venue::factory()->create();
    $parent = Space::factory()->create(['venue_id' => $venue->id]);
    $child = Space::factory()->childOf($parent)->create();

    expect($child->fresh()->parent_space_id)->toBe($parent->id);
});

it('soft-deletes a space via the retired_at column', function () {
    $space = Space::factory()->create();
    $space->delete();

    expect(Space::find($space->id))->toBeNull()
        ->and($space->trashed())->toBeTrue()
        ->and(Space::withTrashed()->find($space->id)->retired_at)->not->toBeNull();
});

it('scopes ->ofKind() to a single kind', function () {
    $venue = Venue::factory()->create();
    Space::factory()->ofKind('ballroom')->create(['venue_id' => $venue->id]);
    Space::factory()->ofKind('stall')->count(3)->create(['venue_id' => $venue->id]);

    expect(Space::query()->ofKind('stall')->count())->toBe(3)
        ->and(Space::query()->ofKind('ballroom')->count())->toBe(1);
});

todo('blocks kind change once the space has historical bookings');
