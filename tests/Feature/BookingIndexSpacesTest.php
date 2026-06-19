<?php

use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders all spaces for each booking on the index', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $spaceA = Space::factory()->for($venue)->create(['name' => 'Hall A']);
    $spaceB = Space::factory()->for($venue)->create(['name' => 'Hall B']);
    $spaceC = Space::factory()->for($venue)->create(['name' => 'Hall C']);
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    foreach ([$spaceA, $spaceB, $spaceC] as $s) {
        BookingSpace::factory()->create([
            'booking_id' => $booking->id,
            'space_id' => $s->id,
            'start_at' => $booking->start_at,
            'end_at' => $booking->end_at,
        ]);
    }

    $this->actingAs($user)
        ->get('/bookings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/index')
            ->has('bookings.data.0.spaces', 3)
        );
});

it('filters the bookings index by start-date window', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    Booking::factory()->create(['venue_id' => $venue->id, 'name' => 'Early', 'start_at' => now()->addDays(5)]);
    Booking::factory()->create(['venue_id' => $venue->id, 'name' => 'Late', 'start_at' => now()->addDays(60)]);

    $from = now()->addDay()->toDateString();
    $to = now()->addDays(30)->toDateString();

    $this->actingAs($user)
        ->get("/bookings?from={$from}&to={$to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/index')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.name', 'Early')
            ->where('filters.from', $from)
            ->where('filters.to', $to)
        );
});
