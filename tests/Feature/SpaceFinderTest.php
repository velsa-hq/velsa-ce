<?php

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\Spaces\SpaceFinder;
use App\Services\Spaces\SpaceFinderCriteria;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->finder = app(SpaceFinder::class);
    $this->venue = Venue::factory()->create();
    $this->window = [
        'starts_at' => CarbonImmutable::parse('2026-09-01 09:00:00'),
        'ends_at' => CarbonImmutable::parse('2026-09-01 17:00:00'),
    ];
});

function findCriteria(array $window, array $overrides = []): SpaceFinderCriteria
{
    return new SpaceFinderCriteria(
        startAt: $window['starts_at'],
        endAt: $window['ends_at'],
        attendance: $overrides['attendance'] ?? null,
        minSqft: $overrides['min_sqft'] ?? null,
        kind: $overrides['kind'] ?? null,
        venueId: $overrides['venue_id'] ?? null,
    );
}

it('returns nothing when no spaces exist', function () {
    expect($this->finder->find(findCriteria($this->window)))->toBeEmpty();
});

it('filters out spaces below the attendance threshold', function () {
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 50]);
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['capacity'])->toBe(200);
});

it('ranks tighter capacity fit ahead of looser fit', function () {
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 1000, 'name' => 'Mega Hall']);
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 120, 'name' => 'Snug Room']);
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 250, 'name' => 'Medium Room']);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results->pluck('name')->all())
        ->toBe(['Snug Room', 'Medium Room', 'Mega Hall']);
});

it('respects the kind filter', function () {
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 200,
        'kind' => 'ballroom',
        'name' => 'Ballroom',
    ]);
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 200,
        'kind' => 'outdoor_field',
        'name' => 'Lawn',
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['kind' => 'ballroom']));

    expect($results)->toHaveCount(1)
        ->and($results->first()['name'])->toBe('Ballroom');
});

it('respects the min_sqft filter', function () {
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200, 'sqft' => 1500]);
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200, 'sqft' => 5000]);

    $results = $this->finder->find(findCriteria($this->window, ['min_sqft' => 3000]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['sqft'])->toBe(5000);
});

it('respects the venue filter', function () {
    $other = Venue::factory()->create();
    Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);
    Space::factory()->create(['venue_id' => $other->id, 'capacity' => 200]);

    $results = $this->finder->find(findCriteria($this->window, ['venue_id' => $this->venue->id]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['venue']['id'])->toBe($this->venue->id);
});

it('excludes spaces with a conflicting definite booking', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);
    $blocked = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);

    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $blocked->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['id'])->toBe($space->id);
});

it('includes spaces with only hold / tentative overlaps', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);

    $booking = Booking::factory()->withStatus(BookingStatus::Hold)->create([
        'venue_id' => $this->venue->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toHaveCount(1);
});

it('excludes spaces with an overlapping space-level blackout', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);
    Blackout::factory()->forSpace($space)->create([
        'starts_at' => $this->window['starts_at']->subDay(),
        'ends_at' => $this->window['ends_at']->addDay(),
        'reason' => 'HVAC maintenance',
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toBeEmpty();
});

it('excludes spaces inside an overlapping venue-wide blackout', function () {
    Space::factory()->count(2)->create(['venue_id' => $this->venue->id, 'capacity' => 200]);
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => $this->window['starts_at']->subDay(),
        'ends_at' => $this->window['ends_at']->addDay(),
        'reason' => 'Venue-wide closure',
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toBeEmpty();
});

it('excludes a child partition space when its parent has a conflicting booking', function () {
    $parent = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 800,
        'name' => 'Grand Ballroom',
    ]);
    $child = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'parent_space_id' => $parent->id,
        'capacity' => 200,
        'name' => 'Section A',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $parent->id,
        'start_at' => $this->window['starts_at'],
        'end_at' => $this->window['ends_at'],
    ]);

    // booking on parent locks the child too
    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));
    $names = $results->pluck('name')->all();

    expect($names)->not->toContain('Section A');
});

it('excludes retired spaces', function () {
    $active = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);
    $retired = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 200,
        'retired_at' => now()->subMonth(),
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['id'])->toBe($active->id);
});

it('shapes each result with the expected keys', function () {
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 150,
        'sqft' => 2000,
        'kind' => 'ballroom',
    ]);

    $results = $this->finder->find(findCriteria($this->window, ['attendance' => 100]));

    expect($results->first())->toHaveKeys([
        'id', 'name', 'venue', 'kind', 'capacity', 'sqft', 'bookable_unit',
        'score', 'rationale',
    ]);
});

it('renders the find page for an authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/spaces/find');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('spaces/find')
        ->has('venues')
        ->has('kinds'));
});

it('runs a search via the controller and returns ranked results', function () {
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 120,
        'name' => 'Snug Room',
    ]);
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'capacity' => 600,
        'name' => 'Big Hall',
    ]);

    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(
        '/spaces/find?'.http_build_query([
            'starts_at' => '2026-09-01 09:00:00',
            'ends_at' => '2026-09-01 17:00:00',
            'attendance' => 100,
        ]),
    );

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('spaces/find')
        ->where('results.0.name', 'Snug Room')
        ->where('results.1.name', 'Big Hall'));
});

it('refuses an ends_at on or before starts_at via the controller', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(
        '/spaces/find?'.http_build_query([
            'starts_at' => '2026-09-01 17:00:00',
            'ends_at' => '2026-09-01 09:00:00',
        ]),
    );

    $response->assertSessionHasErrors('ends_at');
});

it('excludes a space whose buffered turnaround bleeds into the window', function () {
    $venue = Venue::factory()->create(['settings_json' => ['enforce_setup_buffers' => true]]);
    $space = Space::factory()->create(['venue_id' => $venue->id, 'capacity' => 200]);

    // ends 08:30 + 60m teardown -> effective end 09:30, overlaps the 09:00 start
    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $venue->id,
        'start_at' => CarbonImmutable::parse('2026-09-01 06:00:00'),
        'end_at' => CarbonImmutable::parse('2026-09-01 08:30:00'),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => CarbonImmutable::parse('2026-09-01 06:00:00'),
        'end_at' => CarbonImmutable::parse('2026-09-01 08:30:00'),
        'teardown_minutes_after' => 60,
    ]);

    expect($this->finder->find(findCriteria($this->window, ['venue_id' => $venue->id])))
        ->toBeEmpty();
});

it('keeps the space available when the venue does not enforce buffers', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id, 'capacity' => 200]);

    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => CarbonImmutable::parse('2026-09-01 06:00:00'),
        'end_at' => CarbonImmutable::parse('2026-09-01 08:30:00'),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => CarbonImmutable::parse('2026-09-01 06:00:00'),
        'end_at' => CarbonImmutable::parse('2026-09-01 08:30:00'),
        'teardown_minutes_after' => 60,
    ]);

    // raw windows don't overlap and buffers are off -> still bookable
    expect($this->finder->find(findCriteria($this->window, ['venue_id' => $this->venue->id])))
        ->toHaveCount(1);
});
