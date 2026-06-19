<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookingTestUser(): User
{
    return grantSuperAdmin();
}

/**
 * @return array{0: Booking, 1: Venue, 2: Space, 3: Client}
 */
function bookingWithSpace(): array
{
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();
    $booking = Booking::factory()->tentative()->create([
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'start_at' => now()->addDays(30)->setTime(10, 0),
        'end_at' => now()->addDays(30)->setTime(16, 0),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => $booking->start_at,
        'end_at' => $booking->end_at,
    ]);

    return [$booking, $venue, $space, $client];
}

it('renders the show page with booking detail and contracts', function () {
    [$booking] = bookingWithSpace();
    Contract::factory()->create(['booking_id' => $booking->id, 'status' => 'sent']);

    $this->actingAs(bookingTestUser())
        ->get("/bookings/{$booking->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/show')
            ->where('booking.id', $booking->id)
            ->where('booking.reference', $booking->reference)
            ->has('booking.spaces', 1)
            ->has('contracts', 1)
        );
});

it('renders the edit page with the booking values pre-filled', function () {
    [$booking, , $space] = bookingWithSpace();

    $this->actingAs(bookingTestUser())
        ->get("/bookings/{$booking->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/edit')
            ->where('booking.id', $booking->id)
            ->where('booking.name', $booking->name)
            ->where('booking.spaces', [$space->id])
            ->where('statuses', ['inquiry', 'hold', 'tentative', 'definite', 'completed', 'cancelled'])
        );
});

it('updates the booking and stays on the show page', function () {
    [$booking, $venue, $space, $client] = bookingWithSpace();

    $response = $this->actingAs(bookingTestUser())->put("/bookings/{$booking->id}", [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Renamed event',
        'kind' => 'conference',
        'status' => 'definite',
        'start_at' => now()->addDays(31)->setTime(9, 0)->toDateTimeString(),
        'end_at' => now()->addDays(31)->setTime(17, 0)->toDateTimeString(),
        'total_dollars' => '3200.00',
        'spaces' => [$space->id],
    ]);

    $response->assertRedirect("/bookings/{$booking->id}");

    $booking->refresh();
    expect($booking->name)->toBe('Renamed event')
        ->and($booking->status)->toBe(BookingStatus::Definite)
        ->and($booking->total_cents)->toBe(320000);
});

it('syncs spaces on update (drops removed, adds new)', function () {
    [$booking, $venue, $oldSpace, $client] = bookingWithSpace();
    $newSpace = Space::factory()->for($venue)->create();

    $this->actingAs(bookingTestUser())->put("/bookings/{$booking->id}", [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => $booking->name,
        'kind' => 'conference',
        'status' => 'tentative',
        'start_at' => $booking->start_at->toDateTimeString(),
        'end_at' => $booking->end_at->toDateTimeString(),
        'spaces' => [$newSpace->id],
    ])->assertRedirect("/bookings/{$booking->id}");

    $spaceIds = $booking->spaces()->pluck('space_id')->all();
    expect($spaceIds)->toBe([$newSpace->id])
        ->and(BookingSpace::query()->where('booking_id', $booking->id)->where('space_id', $oldSpace->id)->exists())->toBeFalse();
});

it('rejects an update that overlaps a definite booking on the same space', function () {
    [$bookingA, $venue, $space, $client] = bookingWithSpace();

    $blocker = Booking::factory()->definite()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(40)->setTime(10, 0),
        'end_at' => now()->addDays(40)->setTime(16, 0),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $blocker->id,
        'space_id' => $space->id,
        'start_at' => $blocker->start_at,
        'end_at' => $blocker->end_at,
    ]);

    $response = $this->actingAs(bookingTestUser())
        ->from("/bookings/{$bookingA->id}/edit")
        ->put("/bookings/{$bookingA->id}", [
            'venue_id' => $venue->id,
            'client_id' => $client->id,
            'name' => $bookingA->name,
            'kind' => 'conference',
            'status' => 'definite',
            'start_at' => now()->addDays(40)->setTime(12, 0)->toDateTimeString(),
            'end_at' => now()->addDays(40)->setTime(14, 0)->toDateTimeString(),
            'spaces' => [$space->id],
        ]);

    $response->assertRedirect("/bookings/{$bookingA->id}/edit")
        ->assertSessionHasErrors(['spaces']);
});

it('allows cancelling a booking via the update form', function () {
    [$booking, $venue, $space, $client] = bookingWithSpace();

    $this->actingAs(bookingTestUser())->put("/bookings/{$booking->id}", [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => $booking->name,
        'kind' => 'conference',
        'status' => 'cancelled',
        'start_at' => $booking->start_at->toDateTimeString(),
        'end_at' => $booking->end_at->toDateTimeString(),
        'cancel_reason' => 'Client double-booked elsewhere.',
        'spaces' => [$space->id],
    ])->assertRedirect("/bookings/{$booking->id}");

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_at)->not->toBeNull()
        ->and($booking->cancel_reason)->toBe('Client double-booked elsewhere.');
});
