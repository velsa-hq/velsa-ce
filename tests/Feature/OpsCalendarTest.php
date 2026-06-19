<?php

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\Operations\OpsCalendarBuilder;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->builder = app(OpsCalendarBuilder::class);
    $this->venue = Venue::factory()->create(['name' => 'Civic Center']);
    $this->window = [
        CarbonImmutable::parse('2026-09-01'),
        CarbonImmutable::parse('2026-09-30'),
    ];
});

it('returns a venue list for the filter dropdown', function () {
    $data = $this->builder->build(...$this->window);

    expect(collect($data['venues'])->pluck('name')->all())
        ->toContain('Civic Center');
});

it('emits a booking as a FullCalendar event with url + colour', function () {
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'name' => 'Acme Gala',
        'start_at' => '2026-09-10 09:00:00',
        'end_at' => '2026-09-10 22:00:00',
    ]);

    $data = $this->builder->build(...$this->window);
    $event = collect($data['events'])
        ->firstWhere('extendedProps.kind', 'booking');

    expect($event['title'])->toBe('Acme Gala')
        ->and($event['url'])->toStartWith('/bookings/')
        ->and($event['backgroundColor'])->toBe('#10b981')
        ->and($event['extendedProps']['status'])->toBe('definite');
});

it('excludes cancelled + inquiry bookings from the events feed', function () {
    foreach ([BookingStatus::Cancelled, BookingStatus::Inquiry] as $status) {
        Booking::factory()->withStatus($status)->create([
            'venue_id' => $this->venue->id,
            'start_at' => '2026-09-12 09:00:00',
            'end_at' => '2026-09-12 17:00:00',
        ]);
    }

    $data = $this->builder->build(...$this->window);

    expect(collect($data['events'])->where('extendedProps.kind', 'booking'))
        ->toBeEmpty();
});

it('emits a space-level blackout as a background event', function () {
    $space = Space::factory()->create(['venue_id' => $this->venue->id]);
    Blackout::factory()->forSpace($space)->create([
        'starts_at' => '2026-09-04 00:00:00',
        'ends_at' => '2026-09-07 23:59:00',
        'reason' => 'HVAC maintenance',
    ]);

    $data = $this->builder->build(...$this->window);
    $event = collect($data['events'])
        ->firstWhere('extendedProps.kind', 'blackout');

    expect($event['display'])->toBe('background')
        ->and($event['extendedProps']['scope'])->toBe('space')
        ->and($event['extendedProps']['reason'])->toBe('HVAC maintenance');
});

it('emits a venue-level blackout as a background event', function () {
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => '2026-09-04 00:00:00',
        'ends_at' => '2026-09-05 23:59:00',
        'reason' => 'Power outage',
    ]);

    $data = $this->builder->build(...$this->window);
    $event = collect($data['events'])
        ->firstWhere('extendedProps.kind', 'blackout');

    expect($event['extendedProps']['scope'])->toBe('venue');
});

it('filters events by venue_id when provided', function () {
    $other = Venue::factory()->create();
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'name' => 'Mine',
        'start_at' => '2026-09-10 09:00:00',
        'end_at' => '2026-09-10 17:00:00',
    ]);
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $other->id,
        'name' => 'Theirs',
        'start_at' => '2026-09-11 09:00:00',
        'end_at' => '2026-09-11 17:00:00',
    ]);

    $data = $this->builder->build(...[...$this->window, $this->venue->id]);
    $titles = collect($data['events'])
        ->where('extendedProps.kind', 'booking')
        ->pluck('title')
        ->all();

    expect($titles)->toBe(['Mine']);
});

it('renders the calendar page for an authenticated user', function () {
    $user = User::factory()->create();
    $user->assignRoleAt($this->venue, 'ops_lead');
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(3)->addHours(8),
    ]);

    $response = $this->actingAs($user)->get('/ops/calendar');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('ops/calendar')
        ->has('events')
        ->has('venues'));
});

it('respects the venue_id query param on the page', function () {
    $user = User::factory()->create();
    $user->assignRoleAt($this->venue, 'ops_lead');
    $other = Venue::factory()->create();
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $this->venue->id,
        'name' => 'Mine',
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(3)->addHours(8),
    ]);
    Booking::factory()->withStatus(BookingStatus::Definite)->create([
        'venue_id' => $other->id,
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(3)->addHours(8),
    ]);

    $response = $this->actingAs($user)
        ->get('/ops/calendar?venue_id='.$this->venue->id);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('venue_id', $this->venue->id)
        ->where('events', function ($events) {
            $bookings = collect($events)->where('extendedProps.kind', 'booking');

            return $bookings->count() === 1
                && $bookings->first()['title'] === 'Mine';
        }));
});
