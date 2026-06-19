<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\EquipmentItem;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // fully-permissioned user so every result type is searched; RBAC covered in SearchAuthorizationTest
    $this->user = grantSuperAdmin();
});

it('returns an empty groups list for a blank query', function () {
    $response = $this->actingAs($this->user)->getJson('/search?q=');

    $response->assertOk()
        ->assertJson(['query' => '', 'groups' => []]);
});

it('requires authentication on the search endpoint', function () {
    // JSON requests get 401; browser/Inertia requests would redirect to /login
    $this->getJson('/search?q=test')->assertUnauthorized();
});

it('finds a booking by name', function () {
    $venue = Venue::factory()->create(['name' => 'Riverside Hall']);
    Booking::factory()->create([
        'venue_id' => $venue->id,
        'name' => 'Smith-Andersen Wedding Reception',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Smith-Andersen');

    $response->assertOk();
    $body = $response->json();
    $bookingsGroup = collect($body['groups'])->firstWhere('key', 'bookings');

    expect($bookingsGroup)->not->toBeNull()
        ->and($bookingsGroup['results'][0]['title'])
        ->toBe('Smith-Andersen Wedding Reception');
});

it('finds a booking by reference', function () {
    $booking = Booking::factory()->create();

    $response = $this->actingAs($this->user)
        ->getJson('/search?q='.urlencode($booking->reference));

    $bookingsGroup = collect($response->json('groups'))->firstWhere('key', 'bookings');
    expect($bookingsGroup['results'][0]['title'])->toBe($booking->name);
});

it('finds a client by name', function () {
    Client::factory()->create(['name' => 'Northstar Industries Inc']);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Northstar');

    $clientsGroup = collect($response->json('groups'))->firstWhere('key', 'clients');
    expect($clientsGroup['results'][0]['title'])->toBe('Northstar Industries Inc');
});

it('finds an exhibitor by company_name', function () {
    $event = ExhibitorEvent::factory()->create();
    Exhibitor::factory()->create([
        'exhibitor_event_id' => $event->id,
        'company_name' => 'Coastal Trade Outfitters',
        'contact_name' => 'Avery Doe',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Coastal Trade');

    $exhGroup = collect($response->json('groups'))->firstWhere('key', 'exhibitors');
    expect($exhGroup['results'][0]['title'])->toBe('Coastal Trade Outfitters');
});

it('finds a venue by city', function () {
    Venue::factory()->create([
        'name' => 'Eastside Convention Center',
        'address_json' => ['city' => 'Crestview', 'state' => 'FL'],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Crestview');

    $venuesGroup = collect($response->json('groups'))->firstWhere('key', 'venues');
    expect($venuesGroup)->not->toBeNull()
        ->and($venuesGroup['results'][0]['title'])->toBe('Eastside Convention Center');
});

it('finds an equipment item by sku', function () {
    EquipmentItem::factory()->create([
        'sku' => 'CHAIR-FOLD',
        'name' => 'Folding Chair',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=CHAIR-FOLD');

    $eqGroup = collect($response->json('groups'))->firstWhere('key', 'equipment');
    expect($eqGroup['results'][0]['title'])->toBe('Folding Chair');
});

it('caps results per group at 5', function () {
    Client::factory()->count(10)->create([
        'name' => 'Acme Test Co',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Acme Test');

    $clientsGroup = collect($response->json('groups'))->firstWhere('key', 'clients');
    expect(count($clientsGroup['results']))->toBeLessThanOrEqual(5);
});

it('returns multiple groups when the query matches several record types', function () {
    Client::factory()->create(['name' => 'Riverside Bridal']);
    Venue::factory()->create([
        'name' => 'Riverside Hall',
        'address_json' => ['city' => 'Riverside', 'state' => 'CA'],
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Riverside');

    $keys = collect($response->json('groups'))->pluck('key')->all();
    expect($keys)->toContain('clients')
        ->and($keys)->toContain('venues');
});

it('returns a structured payload with required fields per result', function () {
    Client::factory()->create(['name' => 'Schema Test Client']);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=Schema Test');

    $first = $response->json('groups.0.results.0');
    expect($first)->toHaveKeys(['id', 'title', 'subtitle', 'badge', 'url']);
});

it('omits empty groups from the response', function () {
    Client::factory()->create(['name' => 'OnlyClientHere']);

    $response = $this->actingAs($this->user)
        ->getJson('/search?q=OnlyClientHere');

    foreach ($response->json('groups') as $group) {
        expect(count($group['results']))->toBeGreaterThan(0);
    }
});
