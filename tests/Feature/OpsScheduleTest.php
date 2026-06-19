<?php

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\Operations\OpsScheduleBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->builder = app(OpsScheduleBuilder::class);
    $this->user = User::factory()->create();
    $this->venue = Venue::factory()->create(['name' => 'Convention Center']);
    $this->user->assignRoleAt($this->venue, 'ops_lead'); // bookings.view for the page route
    $this->windowStart = CarbonImmutable::parse('2026-09-01 00:00:00');
});

it('returns a 14-day day axis starting at the window start', function () {
    $grid = $this->builder->build($this->windowStart);

    expect($grid['days'])->toHaveCount(14)
        ->and($grid['days'][0]['iso'])->toBe('2026-09-01')
        ->and($grid['days'][13]['iso'])->toBe('2026-09-14');
});

it('marks weekend days', function () {
    // 2026-09-01 is a Tuesday -> Sat 9/5 is index 4, Sun 9/6 is index 5
    $grid = $this->builder->build($this->windowStart);

    expect($grid['days'][4]['is_weekend'])->toBeTrue()
        ->and($grid['days'][5]['is_weekend'])->toBeTrue()
        ->and($grid['days'][0]['is_weekend'])->toBeFalse();
});

it('returns no rows when there are no spaces', function () {
    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'])->toBeEmpty();
});

it('returns a row per space with venue meta', function () {
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Ballroom A',
        'capacity' => 250,
    ]);
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Outdoor Lawn',
        'capacity' => 500,
    ]);

    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'])->toHaveCount(2)
        ->and($grid['rows'][0]['name'])->toBe('Ballroom A')
        ->and($grid['rows'][0]['venue']['name'])->toBe('Convention Center');
});

it('excludes retired spaces', function () {
    Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'Active']);
    Space::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Old Hall',
        'retired_at' => now()->subYear(),
    ]);

    $grid = $this->builder->build($this->windowStart);

    expect(collect($grid['rows'])->pluck('name')->all())->toBe(['Active']);
});

it('filters by venue_id when provided', function () {
    $other = Venue::factory()->create();
    Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'Mine']);
    Space::factory()->create(['venue_id' => $other->id, 'name' => 'Theirs']);

    $grid = $this->builder->build($this->windowStart, $this->venue->id);

    expect($grid['rows'])->toHaveCount(1)
        ->and($grid['rows'][0]['name'])->toBe('Mine');
});

it('places a single-day booking on the correct day index', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-09-03 09:00:00',
        'end_at' => '2026-09-03 17:00:00',
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-09-03 09:00:00',
        'end_at' => '2026-09-03 17:00:00',
    ]);

    $grid = $this->builder->build($this->windowStart);

    $row = $grid['rows'][0];
    expect($row['bookings'])->toHaveCount(1)
        ->and($row['bookings'][0]['start_idx'])->toBe(2)
        ->and($row['bookings'][0]['end_idx'])->toBe(2)
        ->and($row['bookings'][0]['status'])->toBe('definite');
});

it('spans a multi-day booking across the right cells', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-09-02 09:00:00',
        'end_at' => '2026-09-05 17:00:00',
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-09-02 09:00:00',
        'end_at' => '2026-09-05 17:00:00',
    ]);

    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'][0]['bookings'][0]['start_idx'])->toBe(1)
        ->and($grid['rows'][0]['bookings'][0]['end_idx'])->toBe(4);
});

it('clamps booking that started before the window', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-08-28 09:00:00',
        'end_at' => '2026-09-03 17:00:00',
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-08-28 09:00:00',
        'end_at' => '2026-09-03 17:00:00',
    ]);

    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'][0]['bookings'][0]['start_idx'])->toBe(0)
        ->and($grid['rows'][0]['bookings'][0]['end_idx'])->toBe(2);
});

it('clamps booking that ends after the window', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    $booking = Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-09-13 09:00:00',
        'end_at' => '2026-09-20 17:00:00',
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-09-13 09:00:00',
        'end_at' => '2026-09-20 17:00:00',
    ]);

    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'][0]['bookings'][0]['start_idx'])->toBe(12)
        ->and($grid['rows'][0]['bookings'][0]['end_idx'])->toBe(13);
});

it('excludes cancelled and inquiry-status bookings', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);

    foreach ([BookingStatus::Cancelled, BookingStatus::Inquiry] as $status) {
        $booking = Booking::factory()->withStatus($status)->create([
            'venue_id' => $this->venue->id,
            'start_at' => '2026-09-03 09:00:00',
            'end_at' => '2026-09-03 17:00:00',
        ]);
        BookingSpace::factory()->create([
            'booking_id' => $booking->id,
            'space_id' => $space->id,
            'start_at' => '2026-09-03 09:00:00',
            'end_at' => '2026-09-03 17:00:00',
        ]);
    }

    $grid = $this->builder->build($this->windowStart);

    expect($grid['rows'][0]['bookings'])->toBeEmpty();
});

it('surfaces a space-level blackout on the right cells', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    Blackout::factory()->forSpace($space)->create([
        'starts_at' => '2026-09-04 00:00:00',
        'ends_at' => '2026-09-06 23:59:00',
        'reason' => 'HVAC',
    ]);

    $grid = $this->builder->build($this->windowStart);

    $bl = $grid['rows'][0]['blackouts'][0];
    expect($bl['reason'])->toBe('HVAC')
        ->and($bl['scope'])->toBe('space')
        ->and($bl['start_idx'])->toBe(3)
        ->and($bl['end_idx'])->toBe(5);
});

it('surfaces a venue-level blackout against every space in the venue', function () {
    Space::factory()->count(3)->create(['venue_id' => $this->venue->id]);
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => '2026-09-04 00:00:00',
        'ends_at' => '2026-09-05 23:59:00',
        'reason' => 'Power outage',
    ]);

    $grid = $this->builder->build($this->windowStart);

    foreach ($grid['rows'] as $row) {
        expect($row['blackouts'])->toHaveCount(1)
            ->and($row['blackouts'][0]['scope'])->toBe('venue');
    }
});

it('renders the schedule page for an authenticated user', function () {
    Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'A space']);

    $response = $this->actingAs($this->user)->get('/ops/schedule');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ops/schedule')
        ->has('days', 14)
        ->has('rows')
        ->has('venues'));
});

it('respects the from query param', function () {
    $response = $this->actingAs($this->user)->get('/ops/schedule?from=2026-10-15');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('window_start', '2026-10-15')
        ->where('days.0.iso', '2026-10-15'));
});

it('respects the venue_id query param', function () {
    $other = Venue::factory()->create();
    Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'Mine']);
    Space::factory()->create(['venue_id' => $other->id, 'name' => 'Theirs']);

    $response = $this->actingAs($this->user)
        ->get('/ops/schedule?from=2026-09-01&venue_id='.$this->venue->id);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('rows', 1)
        ->where('rows.0.name', 'Mine'));
});
