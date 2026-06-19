<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A booking_space on $space for the window with no buffers, under a booking of the given status. */
function spaceBooking(Space $space, BookingStatus $status): Booking
{
    $booking = Booking::factory()->withStatus($status)->create([
        'venue_id' => $space->venue_id,
        'start_at' => '2026-10-10 09:00:00',
        'end_at' => '2026-10-10 17:00:00',
    ]);

    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => '2026-10-10 09:00:00',
        'end_at' => '2026-10-10 17:00:00',
        'setup_minutes_before' => 0,
        'teardown_minutes_after' => 0,
    ]);

    return $booking;
}

it('marks a booking-space blocking only when its booking is blocking', function () {
    $space = Space::factory()->create();

    $hold = spaceBooking($space, BookingStatus::Hold);
    expect($hold->spaces()->first()->blocks_overlap)->toBeFalse();

    $definite = Booking::factory()->definite()->create(['venue_id' => $space->venue_id]);
    $bs = BookingSpace::factory()->create([
        'booking_id' => $definite->id,
        'space_id' => Space::factory()->create()->id, // different space
        'start_at' => '2026-11-01 09:00:00',
        'end_at' => '2026-11-01 17:00:00',
        'setup_minutes_before' => 0,
        'teardown_minutes_after' => 0,
    ]);
    expect($bs->blocks_overlap)->toBeTrue();
});

it('syncs the blocking flag when a booking is confirmed', function () {
    $space = Space::factory()->create();
    $hold = spaceBooking($space, BookingStatus::Hold);

    expect($hold->spaces()->first()->blocks_overlap)->toBeFalse();

    $hold->update(['status' => BookingStatus::Definite->value]);

    expect($hold->spaces()->first()->blocks_overlap)->toBeTrue();
});

it('refuses to confirm a hold that overlaps an already-confirmed booking', function () {
    $space = Space::factory()->create();

    // two holds can coexist on the same window
    $first = spaceBooking($space, BookingStatus::Hold);
    $second = spaceBooking($space, BookingStatus::Hold);

    $first->update(['status' => BookingStatus::Definite->value]);
    expect($first->refresh()->status)->toBe(BookingStatus::Definite);

    // confirming the second collides with the definite first; guard throws a clean error, not a DB violation
    expect(fn () => $second->update(['status' => BookingStatus::Definite->value]))
        ->toThrow(RuntimeException::class);

    expect($second->refresh()->status)->toBe(BookingStatus::Hold);
});

it('allows confirming a hold when nothing else blocks the window', function () {
    $space = Space::factory()->create();
    $hold = spaceBooking($space, BookingStatus::Hold);

    $hold->update(['status' => BookingStatus::Definite->value]);

    expect($hold->refresh()->status)->toBe(BookingStatus::Definite);
});
