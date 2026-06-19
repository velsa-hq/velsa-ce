<?php

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends a draft contract with signer info', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    $contract = Contract::factory()->create([
        'booking_id' => $booking->id,
        'status' => ContractStatus::Draft->value,
    ]);

    $response = $this->actingAs($user)
        ->from("/bookings/{$booking->id}")
        ->post("/contracts/{$contract->id}/send", [
            'signers' => [
                ['name' => 'Pat Client', 'email' => 'pat@example.test', 'role' => 'client'],
            ],
        ]);

    $response->assertRedirect("/bookings/{$booking->id}");
    expect($contract->fresh()->status)->toBe(ContractStatus::Sent);
});

it('rejects sending a non-draft contract', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    $contract = Contract::factory()->create([
        'booking_id' => $booking->id,
        'status' => ContractStatus::Sent->value,
    ]);

    $this->actingAs($user)
        ->post("/contracts/{$contract->id}/send", [
            'signers' => [
                ['name' => 'Pat Client', 'email' => 'pat@example.test'],
            ],
        ])
        ->assertStatus(422);
});

it('validates signers (required, at least one, email)', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    $contract = Contract::factory()->create([
        'booking_id' => $booking->id,
        'status' => ContractStatus::Draft->value,
    ]);

    $this->actingAs($user)
        ->post("/contracts/{$contract->id}/send", [])
        ->assertSessionHasErrors(['signers']);

    $this->actingAs($user)
        ->post("/contracts/{$contract->id}/send", [
            'signers' => [['name' => 'Pat', 'email' => 'not-an-email']],
        ])
        ->assertSessionHasErrors(['signers.0.email']);
});
