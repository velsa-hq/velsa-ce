<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefills the create form when from_lead is supplied', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $client = Client::factory()->create();
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'stage' => 'won',
        'name' => 'Spring 2027 Gala',
        'estimated_value_cents' => 850_000,
    ]);

    $this->actingAs($user)
        ->get("/bookings/create?from_lead={$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bookings/create')
            ->where('from_lead.id', $lead->id)
            ->where('from_lead.name', 'Spring 2027 Gala')
            ->where('from_lead.client_id', $client->id)
            ->where('from_lead.venue_id', $venue->id)
            ->where('from_lead.estimated_value_cents', 850_000)
        );
});

it('drops the from_lead prefill if the lead has already been converted', function () {
    $user = grantSuperAdmin();
    $existing = Booking::factory()->create();
    $lead = Lead::factory()->create([
        'stage' => 'won',
        'converted_booking_id' => $existing->id,
        'converted_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/bookings/create?from_lead={$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('from_lead', null));
});

it('stamps the lead converted_at + converted_booking_id on booking creation', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'stage' => 'won',
    ]);

    $response = $this->actingAs($user)->post('/bookings', [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Converted booking',
        'kind' => 'conference',
        'status' => 'tentative',
        'start_at' => now()->addDays(20)->setTime(10, 0)->toDateTimeString(),
        'end_at' => now()->addDays(20)->setTime(16, 0)->toDateTimeString(),
        'lead_id' => $lead->id,
        'spaces' => [$space->id],
    ]);

    $booking = Booking::query()->where('name', 'Converted booking')->firstOrFail();
    $response->assertRedirect("/bookings/{$booking->id}");

    $lead->refresh();
    expect($lead->converted_booking_id)->toBe($booking->id)
        ->and($lead->converted_at)->not->toBeNull()
        ->and($booking->lead_id)->toBe($lead->id);
});

it('does not overwrite a prior conversion if the same lead is sent again', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create();
    $client = Client::factory()->create();
    $firstBooking = Booking::factory()->create();
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'stage' => 'won',
        'converted_booking_id' => $firstBooking->id,
        'converted_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->post('/bookings', [
        'venue_id' => $venue->id,
        'client_id' => $client->id,
        'name' => 'Second attempt',
        'kind' => 'conference',
        'status' => 'tentative',
        'start_at' => now()->addDays(20)->setTime(10, 0)->toDateTimeString(),
        'end_at' => now()->addDays(20)->setTime(16, 0)->toDateTimeString(),
        'lead_id' => $lead->id,
        'spaces' => [$space->id],
    ]);

    $lead->refresh();
    // still points at the first booking, not the one we just created
    expect($lead->converted_booking_id)->toBe($firstBooking->id);
});
