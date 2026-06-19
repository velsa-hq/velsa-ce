<?php

use App\Enums\ClientType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the client show page with related collections', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create([
        'name' => 'Acme Tourism Co',
        'type' => ClientType::Business->value,
    ]);
    $contact = Contact::factory()->create([
        'client_id' => $client->id,
        'is_primary' => true,
    ]);
    $client->update(['primary_contact_id' => $contact->id]);

    $booking = Booking::factory()->create(['client_id' => $client->id]);
    Lead::factory()->create(['client_id' => $client->id, 'stage' => 'qualified']);
    Contract::factory()->create(['booking_id' => $booking->id]);

    $this->actingAs($user)
        ->get("/clients/{$client->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clients/show')
            ->where('client.id', $client->id)
            ->where('client.name', 'Acme Tourism Co')
            ->has('client.primary_contact')
            ->has('client.contacts', 1)
            ->has('bookings', 1)
            ->has('leads', 1)
            ->has('contracts', 1)
        );
});

it('renders the edit page with types and current values', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create([
        'name' => 'Test Co',
        'type' => ClientType::Nonprofit->value,
        'industry' => 'Education',
    ]);

    $this->actingAs($user)
        ->get("/clients/{$client->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clients/edit')
            ->where('client.id', $client->id)
            ->where('client.name', 'Test Co')
            ->where('client.type', 'nonprofit')
            ->where('types', ['individual', 'business', 'government', 'nonprofit', 'educational'])
        );
});

it('updates a client and redirects to the show page', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create([
        'name' => 'Old Name',
        'type' => ClientType::Business->value,
    ]);

    $this->actingAs($user)
        ->put("/clients/{$client->id}", [
            'name' => 'New Name',
            'type' => 'nonprofit',
            'industry' => 'Religious',
            'source' => 'referral',
            'notes' => 'Switched to nonprofit status this year.',
        ])
        ->assertRedirect("/clients/{$client->id}");

    $client->refresh();
    expect($client->name)->toBe('New Name')
        ->and($client->type)->toBe(ClientType::Nonprofit)
        ->and($client->industry)->toBe('Religious');
});

it('rejects updates with an invalid type', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create();

    $this->actingAs($user)
        ->put("/clients/{$client->id}", [
            'name' => 'Test',
            'type' => 'not-a-real-type',
        ])
        ->assertSessionHasErrors(['type']);
});

it('returns 404 for an unknown client id', function () {
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/clients/999999')
        ->assertNotFound();
});
