<?php

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venue = Venue::factory()->create();
    $this->space = Space::factory()->create(['venue_id' => $this->venue->id]);
});

it('blocks a new booking_space that overlaps a venue-wide blackout', function () {
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => '2026-08-04 00:00:00',
        'ends_at' => '2026-08-06 23:59:00',
        'reason' => 'HVAC maintenance',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-08-05 09:00:00',
        'end_at' => '2026-08-05 17:00:00',
    ]);

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-08-05 09:00:00',
        'end_at' => '2026-08-05 17:00:00',
    ]))->toThrow(RuntimeException::class, 'HVAC maintenance');
});

it('blocks a booking_space that overlaps a space-specific blackout', function () {
    Blackout::factory()->forSpace($this->space)->create([
        'starts_at' => '2026-09-01 00:00:00',
        'ends_at' => '2026-09-02 00:00:00',
        'reason' => 'Carpet replacement',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Hold)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-09-01 12:00:00',
        'end_at' => '2026-09-01 18:00:00',
    ]);

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-09-01 12:00:00',
        'end_at' => '2026-09-01 18:00:00',
    ]))->toThrow(RuntimeException::class, 'Carpet replacement');
});

it('blocks a child-space booking when a parent-space blackout overlaps', function () {
    $parent = Space::factory()->create(['venue_id' => $this->venue->id, 'name' => 'Grand Ballroom']);
    $child = Space::factory()->create([
        'venue_id' => $this->venue->id,
        'name' => 'Section A',
        'parent_space_id' => $parent->id,
    ]);

    Blackout::factory()->forSpace($parent)->create([
        'starts_at' => '2026-10-01 00:00:00',
        'ends_at' => '2026-10-03 00:00:00',
        'reason' => 'Repainting',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-10-02 09:00:00',
        'end_at' => '2026-10-02 17:00:00',
    ]);

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $child->id,
        'start_at' => '2026-10-02 09:00:00',
        'end_at' => '2026-10-02 17:00:00',
    ]))->toThrow(RuntimeException::class, 'Repainting');
});

it('allows a booking that ends before a blackout starts', function () {
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => '2026-11-10 00:00:00',
        'ends_at' => '2026-11-12 00:00:00',
        'reason' => 'Annual deep clean',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-11-09 09:00:00',
        'end_at' => '2026-11-09 17:00:00',
    ]);

    $bs = BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-11-09 09:00:00',
        'end_at' => '2026-11-09 17:00:00',
    ]);

    expect($bs->id)->not->toBeNull();
});

it('allows a booking that starts exactly when a blackout ends (half-open semantics)', function () {
    Blackout::factory()->forVenue($this->venue)->create([
        'starts_at' => '2026-12-01 00:00:00',
        'ends_at' => '2026-12-02 12:00:00',
        'reason' => 'Half-day closure',
    ]);

    $booking = Booking::factory()->withStatus(BookingStatus::Tentative)->create([
        'venue_id' => $this->venue->id,
        'start_at' => '2026-12-02 12:00:00',
        'end_at' => '2026-12-02 18:00:00',
    ]);

    $bs = BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-12-02 12:00:00',
        'end_at' => '2026-12-02 18:00:00',
    ]);

    expect($bs->id)->not->toBeNull();
});

it('creates a venue-wide blackout via the admin endpoint', function () {
    $admin = grantSuperAdmin();

    $response = $this->actingAs($admin)->post(
        "/venues/{$this->venue->slug}/blackouts",
        [
            'starts_at' => '2026-07-04 00:00:00',
            'ends_at' => '2026-07-05 00:00:00',
            'reason' => 'County holiday',
        ],
    );

    $response->assertSessionDoesntHaveErrors()->assertRedirect();
    expect(Blackout::query()->where('reason', 'County holiday')->first()->blackoutable_type)
        ->toBe(Venue::class);
});

it('creates a space-scoped blackout when space_id is provided', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->post(
        "/venues/{$this->venue->slug}/blackouts",
        [
            'space_id' => $this->space->id,
            'starts_at' => '2026-07-06 00:00:00',
            'ends_at' => '2026-07-07 00:00:00',
            'reason' => 'Floor refinishing',
        ],
    )->assertRedirect();

    $blackout = Blackout::query()->where('reason', 'Floor refinishing')->firstOrFail();
    expect($blackout->blackoutable_type)->toBe(Space::class)
        ->and($blackout->blackoutable_id)->toBe($this->space->id);
});

it('rejects an end-before-start window at the admin endpoint', function () {
    $admin = grantSuperAdmin();

    $response = $this->actingAs($admin)->post(
        "/venues/{$this->venue->slug}/blackouts",
        [
            'starts_at' => '2026-08-10 00:00:00',
            'ends_at' => '2026-08-09 00:00:00',
            'reason' => 'Bad window',
        ],
    );

    $response->assertSessionHasErrors('ends_at');
    expect(Blackout::query()->count())->toBe(0);
});

it('deletes a blackout via the admin endpoint', function () {
    $admin = grantSuperAdmin();
    $blackout = Blackout::factory()->forVenue($this->venue)->create();

    $this->actingAs($admin)
        ->delete("/venues/{$this->venue->slug}/blackouts/{$blackout->id}")
        ->assertRedirect();

    expect(Blackout::query()->find($blackout->id))->toBeNull();
});

it('refuses to delete a blackout that belongs to a different venue', function () {
    $admin = grantSuperAdmin();
    $otherVenue = Venue::factory()->create();
    $blackout = Blackout::factory()->forVenue($otherVenue)->create();

    $this->actingAs($admin)
        ->delete("/venues/{$this->venue->slug}/blackouts/{$blackout->id}")
        ->assertNotFound();

    expect(Blackout::query()->find($blackout->id))->not->toBeNull();
});

it('stamps the creating user id on a blackout', function () {
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->post(
        "/venues/{$this->venue->slug}/blackouts",
        [
            'starts_at' => '2026-07-04 00:00:00',
            'ends_at' => '2026-07-05 00:00:00',
            'reason' => 'County holiday',
        ],
    );

    expect(Blackout::query()->latest('id')->first()->created_by_user_id)->toBe($admin->id);
});
