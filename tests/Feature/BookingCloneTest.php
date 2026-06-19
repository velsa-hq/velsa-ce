<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('clones a booking into a fresh inquiry', function () {
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $client = Client::factory()->create();
    $booking = Booking::factory()->create([
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Harvest Gala',
        'status' => 'definite',
        'total_cents' => 4_200_00,
    ]);

    $this->actingAs($admin)
        ->post("/bookings/{$booking->id}/clone")
        ->assertRedirect();

    $clone = Booking::query()->where('name', 'Harvest Gala (Copy)')->first();

    expect($clone)->not->toBeNull()
        ->and($clone->id)->not->toBe($booking->id)
        ->and($clone->status->value)->toBe('inquiry')
        ->and($clone->reference)->not->toBe($booking->reference)
        ->and($clone->client_id)->toBe($client->id)
        ->and($clone->venue_id)->toBe($venue->id)
        ->and($clone->total_cents)->toBe($booking->total_cents);
});
