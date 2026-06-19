<?php

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('drafts a contract from a booking and redirects back to the show page', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->tentative()->create([
        'venue_id' => $venue->id,
        'total_cents' => 250_000,
    ]);

    $response = $this->actingAs($user)->post("/bookings/{$booking->id}/contracts");

    $response->assertRedirect("/bookings/{$booking->id}");

    $contract = Contract::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($contract->status)->toBe(ContractStatus::Draft)
        ->and($contract->kind)->toBe('contract')
        ->and($contract->total_cents)->toBe(250_000)
        ->and($contract->reference)->toStartWith('CT-');
});

it('honors a kind query when drafting (proposal vs contract)', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->tentative()->create();

    $this->actingAs($user)
        ->post("/bookings/{$booking->id}/contracts", ['kind' => 'proposal'])
        ->assertRedirect("/bookings/{$booking->id}");

    expect(Contract::query()->where('booking_id', $booking->id)->where('kind', 'proposal')->exists())->toBeTrue();
});

it('allows drafting multiple contracts for the same booking', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->tentative()->create();

    $this->actingAs($user)->post("/bookings/{$booking->id}/contracts");
    $this->actingAs($user)->post("/bookings/{$booking->id}/contracts");

    expect(Contract::query()->where('booking_id', $booking->id)->count())->toBe(2);
});
