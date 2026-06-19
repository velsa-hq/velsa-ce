<?php

use App\Enums\BookingStatus;
use App\Enums\ClientType;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Client;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function actingUser(): User
{
    return grantSuperAdmin();
}

it('prefills the venue + space when arriving from a space page', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();

    $this->actingAs($user)
        ->get("/bookings/create?venue_id={$venue->id}&space_id={$space->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/create')
            ->where('prefill.venue_id', $venue->id)
            ->where('prefill.space_id', $space->id)
            ->where('from_lead', null));
});

it('has no prefill on a plain create', function () {
    $user = actingUser();

    $this->actingAs($user)
        ->get('/bookings/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('prefill', null));
});

it('renders the create form with active venues, clients, kinds, and statuses', function () {
    // role at this venue; grantSuperAdmin would add a second active venue and break the count
    $this->seed(RolesAndPermissionsSeeder::class);
    $venue = Venue::factory()->create();
    $user = User::factory()->create();
    $user->assignRoleAt($venue, 'super_admin');
    Space::factory()->for($venue)->create(['name' => 'Grand Ballroom']);
    Client::factory()->create(['name' => 'Acme Co']);

    $this->actingAs($user)
        ->get('/bookings/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/create')
            ->has('venues', 1)
            ->where('venues.0.spaces.0.name', 'Grand Ballroom')
            ->has('clients', 1)
            ->where('clients.0.name', 'Acme Co')
            ->where('creatable_statuses', ['inquiry', 'hold', 'tentative', 'definite'])
            ->where('client_types', ['individual', 'business', 'government', 'nonprofit', 'educational'])
        );
});

it('creates a booking with spaces and converts dollars to cents', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();

    $response = $this->actingAs($user)->post('/bookings', [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Test Wedding',
        'kind' => 'wedding',
        'status' => 'tentative',
        'start_at' => now()->addDays(30)->setTime(10, 0)->toDateTimeString(),
        'end_at' => now()->addDays(30)->setTime(16, 0)->toDateTimeString(),
        'attendance_estimate' => 150,
        'total_dollars' => 2500.50,
        'notes' => 'Outdoor ceremony, indoor reception.',
        'spaces' => [$space->id],
    ]);

    $booking = Booking::query()->where('name', 'Test Wedding')->firstOrFail();
    $response->assertRedirect("/bookings/{$booking->id}");

    expect($booking->status)->toBe(BookingStatus::Tentative)
        ->and($booking->reference)->toStartWith('BK-')
        ->and($booking->owner_user_id)->toBe($user->id)
        ->and($booking->total_cents)->toBe(250050);

    expect(BookingSpace::query()->where('booking_id', $booking->id)->where('space_id', $space->id)->exists())->toBeTrue();
});

it('can create the client inline at the same time as the booking', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();

    $response = $this->actingAs($user)->post('/bookings', [
        'venue_id' => $venue->id,
        'new_client' => [
            'name' => 'Fresh Co',
            'type' => 'business',
            'email' => 'contact@fresh.test',
        ],
        'name' => 'New-client booking',
        'kind' => 'conference',
        'status' => 'inquiry',
        'start_at' => now()->addDays(20)->setTime(9, 0)->toDateTimeString(),
        'end_at' => now()->addDays(20)->setTime(17, 0)->toDateTimeString(),
        'spaces' => [$space->id],
    ]);

    $client = Client::query()->where('name', 'Fresh Co')->firstOrFail();
    $booking = Booking::query()->where('client_id', $client->id)->firstOrFail();
    $response->assertRedirect("/bookings/{$booking->id}");

    expect($client->type)->toBe(ClientType::Business)
        ->and($client->primaryContact?->email)->toBe('contact@fresh.test');

    expect(Booking::query()->where('client_id', $client->id)->where('name', 'New-client booking')->exists())->toBeTrue();
});

it('requires exactly one of client_id or new_client.name', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post('/bookings', [
            'venue_id' => $venue->id,
            'client_id' => $client->id,
            'new_client' => ['name' => 'Also Co', 'type' => 'business'],
            'name' => 'Both supplied',
            'kind' => 'conference',
            'status' => 'inquiry',
            'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(7)->setTime(12, 0)->toDateTimeString(),
            'spaces' => [$space->id],
        ])
        ->assertSessionHasErrors(['new_client.name']);
});

it('validates required fields', function () {
    $user = actingUser();

    $this->actingAs($user)
        ->post('/bookings', [])
        ->assertSessionHasErrors(['venue_id', 'client_id', 'name', 'kind', 'status', 'start_at', 'end_at', 'spaces']);
});

it('rejects an end_at that is not after start_at', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post('/bookings', [
            'venue_id' => $venue->id,
            'client_id' => $client->id,
            'name' => 'Bad times',
            'kind' => 'conference',
            'status' => 'inquiry',
            'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(7)->setTime(9, 0)->toDateTimeString(),
            'spaces' => [$space->id],
        ])
        ->assertSessionHasErrors(['end_at']);
});

it('rejects spaces that do not belong to the chosen venue', function () {
    $user = actingUser();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    $spaceOfB = Space::factory()->for($venueB)->create();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->post('/bookings', [
            'venue_id' => $venueA->id,
            'client_id' => $client->id,
            'name' => 'Mismatched venue/space',
            'kind' => 'conference',
            'status' => 'inquiry',
            'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
            'end_at' => now()->addDays(7)->setTime(12, 0)->toDateTimeString(),
            'spaces' => [$spaceOfB->id],
        ])
        ->assertSessionHasErrors(['spaces']);
});

it('rejects a definite booking that overlaps an existing definite on the same space', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();

    $existing = Booking::factory()->definite()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(15)->setTime(10, 0),
        'end_at' => now()->addDays(15)->setTime(16, 0),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $existing->id,
        'space_id' => $space->id,
        'start_at' => $existing->start_at,
        'end_at' => $existing->end_at,
    ]);

    $response = $this->actingAs($user)->from('/bookings/create')->post('/bookings', [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Conflict booking',
        'kind' => 'conference',
        'status' => 'definite',
        'start_at' => now()->addDays(15)->setTime(12, 0)->toDateTimeString(),
        'end_at' => now()->addDays(15)->setTime(14, 0)->toDateTimeString(),
        'spaces' => [$space->id],
    ]);

    $response->assertRedirect('/bookings/create')
        ->assertSessionHasErrors(['spaces']);

    expect(Booking::query()->where('name', 'Conflict booking')->exists())->toBeFalse();
});

it('does not allow creating with a Completed or Cancelled status', function () {
    $user = actingUser();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();

    foreach (['completed', 'cancelled'] as $disallowed) {
        $this->actingAs($user)
            ->post('/bookings', [
                'venue_id' => $venue->id,
                'client_id' => $client->id,
                'name' => 'Disallowed status',
                'kind' => 'conference',
                'status' => $disallowed,
                'start_at' => now()->addDays(7)->setTime(10, 0)->toDateTimeString(),
                'end_at' => now()->addDays(7)->setTime(12, 0)->toDateTimeString(),
                'spaces' => [$space->id],
            ])
            ->assertSessionHasErrors(['status']);
    }
});
