<?php

use App\Enums\BookingStatus;
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

function makeBooking(Venue $venue, BookingStatus $status, string $start, string $end): Booking
{
    return Booking::factory()->withStatus($status)->create([
        'venue_id' => $venue->id,
        'start_at' => $start,
        'end_at' => $end,
    ]);
}

it('blocks a booking_space overlapping a definite booking on the same space', function () {
    $existing = makeBooking($this->venue, BookingStatus::Definite, '2026-08-01 09:00:00', '2026-08-01 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $existing->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-08-01 09:00:00',
        'end_at' => '2026-08-01 17:00:00',
    ]);

    $conflict = makeBooking($this->venue, BookingStatus::Tentative, '2026-08-01 13:00:00', '2026-08-01 20:00:00');

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $conflict->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-08-01 13:00:00',
        'end_at' => '2026-08-01 20:00:00',
    ]))->toThrow(RuntimeException::class, 'already booked');
});

it('allows two non-blocking bookings to overlap on the same space', function () {
    $hold = makeBooking($this->venue, BookingStatus::Hold, '2026-09-01 09:00:00', '2026-09-01 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $hold->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-09-01 09:00:00',
        'end_at' => '2026-09-01 17:00:00',
    ]);

    $tentative = makeBooking($this->venue, BookingStatus::Tentative, '2026-09-01 10:00:00', '2026-09-01 14:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $tentative->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-09-01 10:00:00',
        'end_at' => '2026-09-01 14:00:00',
    ]);

    expect(BookingSpace::query()->count())->toBe(2);
});

it('rejects a definite booking locking a window already held', function () {
    $hold = makeBooking($this->venue, BookingStatus::Hold, '2026-10-01 09:00:00', '2026-10-01 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $hold->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-10-01 09:00:00',
        'end_at' => '2026-10-01 17:00:00',
    ]);

    $definite = makeBooking($this->venue, BookingStatus::Definite, '2026-10-01 12:00:00', '2026-10-01 14:00:00');

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $definite->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-10-01 12:00:00',
        'end_at' => '2026-10-01 14:00:00',
    ]))->toThrow(RuntimeException::class);
});

it('allows non-overlapping bookings even when both are definite', function () {
    $morning = makeBooking($this->venue, BookingStatus::Definite, '2026-11-01 09:00:00', '2026-11-01 12:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $morning->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-11-01 09:00:00',
        'end_at' => '2026-11-01 12:00:00',
    ]);

    $afternoon = makeBooking($this->venue, BookingStatus::Definite, '2026-11-01 13:00:00', '2026-11-01 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $afternoon->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-11-01 13:00:00',
        'end_at' => '2026-11-01 17:00:00',
    ]);

    expect(BookingSpace::query()->count())->toBe(2);
});

it('allows re-saving a definite booking without tripping its own overlap check', function () {
    $booking = makeBooking($this->venue, BookingStatus::Definite, '2026-12-01 09:00:00', '2026-12-01 17:00:00');
    $bs = BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-12-01 09:00:00',
        'end_at' => '2026-12-01 17:00:00',
    ]);

    $bs->notes = 'updated';

    expect(fn () => $bs->save())->not->toThrow(RuntimeException::class);
});

it('allows overlapping bookings on DIFFERENT spaces in the same venue', function () {
    $spaceB = Space::factory()->create(['venue_id' => $this->venue->id]);

    $bookingA = makeBooking($this->venue, BookingStatus::Definite, '2026-12-15 09:00:00', '2026-12-15 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $bookingA->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-12-15 09:00:00',
        'end_at' => '2026-12-15 17:00:00',
    ]);

    $bookingB = makeBooking($this->venue, BookingStatus::Definite, '2026-12-15 09:00:00', '2026-12-15 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $bookingB->id,
        'space_id' => $spaceB->id,
        'start_at' => '2026-12-15 09:00:00',
        'end_at' => '2026-12-15 17:00:00',
    ]);

    expect(BookingSpace::query()->count())->toBe(2);
});

it('reserves setup-buffer time as occupied when the venue enforces buffers', function () {
    $venue = Venue::factory()->create(['settings_json' => ['enforce_setup_buffers' => true]]);
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    $existing = makeBooking($venue, BookingStatus::Definite, '2026-11-01 09:00:00', '2026-11-01 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $existing->id,
        'space_id' => $space->id,
        'start_at' => '2026-11-01 09:00:00',
        'end_at' => '2026-11-01 17:00:00',
    ]);

    // starts when the other ends, but 90 min setup backs into the existing booking
    $next = makeBooking($venue, BookingStatus::Tentative, '2026-11-01 17:00:00', '2026-11-01 19:00:00');

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $next->id,
        'space_id' => $space->id,
        'start_at' => '2026-11-01 17:00:00',
        'end_at' => '2026-11-01 19:00:00',
        'setup_minutes_before' => 90,
    ]))->toThrow(RuntimeException::class, 'already booked');
});

it('allows the same back-to-back booking when the venue does not enforce buffers', function () {
    $existing = makeBooking($this->venue, BookingStatus::Definite, '2026-11-02 09:00:00', '2026-11-02 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $existing->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-11-02 09:00:00',
        'end_at' => '2026-11-02 17:00:00',
    ]);

    $next = makeBooking($this->venue, BookingStatus::Tentative, '2026-11-02 17:00:00', '2026-11-02 19:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $next->id,
        'space_id' => $this->space->id,
        'start_at' => '2026-11-02 17:00:00',
        'end_at' => '2026-11-02 19:00:00',
        'setup_minutes_before' => 90,
    ]);

    expect(BookingSpace::query()->where('space_id', $this->space->id)->count())->toBe(2);
});

it('counts an existing booking teardown buffer against a follower', function () {
    $venue = Venue::factory()->create(['settings_json' => ['enforce_setup_buffers' => true]]);
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    $existing = makeBooking($venue, BookingStatus::Definite, '2026-11-03 09:00:00', '2026-11-03 17:00:00');
    BookingSpace::factory()->create([
        'booking_id' => $existing->id,
        'space_id' => $space->id,
        'start_at' => '2026-11-03 09:00:00',
        'end_at' => '2026-11-03 17:00:00',
        'teardown_minutes_after' => 60,
    ]);

    // starts 17:30, inside the existing booking's 17:00-18:00 teardown
    $next = makeBooking($venue, BookingStatus::Tentative, '2026-11-03 17:30:00', '2026-11-03 20:00:00');

    expect(fn () => BookingSpace::factory()->create([
        'booking_id' => $next->id,
        'space_id' => $space->id,
        'start_at' => '2026-11-03 17:30:00',
        'end_at' => '2026-11-03 20:00:00',
    ]))->toThrow(RuntimeException::class, 'already booked');
});
