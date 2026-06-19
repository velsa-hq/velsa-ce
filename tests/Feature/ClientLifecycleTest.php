<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // client CRUD requires clients.manage; use a privileged user
    $this->user = grantSuperAdmin();
});

// ---------- Create ----------

it('creates a client with address, tax id, custom fields, and a primary contact', function () {
    $this->actingAs($this->user)->post('/clients', [
        'name' => 'Harbor Lights LLC',
        'type' => 'business',
        'industry' => 'Hospitality',
        'source' => 'referral',
        'tax_id' => '12-3456789',
        'address' => ['street' => '1 Bay St', 'city' => 'Pelican Cove', 'state' => 'FL', 'postal_code' => '32501'],
        'custom_fields' => [
            ['key' => 'Preferred AV', 'value' => 'In-house'],
            ['key' => '', 'value' => 'dropped'],
        ],
        'contact' => ['name' => 'Dana Reef', 'role' => 'Owner', 'email' => 'dana@harbor.test', 'phone' => '555-1000'],
    ])->assertRedirect();

    $client = Client::query()->where('name', 'Harbor Lights LLC')->sole();

    expect($client->tax_id_encrypted)->toBe('12-3456789')
        ->and($client->address_json['city'])->toBe('Pelican Cove')
        ->and($client->custom_fields_json)->toBe(['Preferred AV' => 'In-house'])
        ->and($client->contacts()->count())->toBe(1)
        ->and($client->primaryContact?->name)->toBe('Dana Reef')
        ->and($client->primaryContact?->is_primary)->toBeTrue();
});

it('warns (non-blocking) when creating a duplicate-named client', function () {
    Client::factory()->create(['name' => 'Twin Co']);

    $this->actingAs($this->user)->post('/clients', [
        'name' => 'Twin Co',
        'type' => 'business',
    ])->assertRedirect();

    expect(Client::query()->where('name', 'Twin Co')->count())->toBe(2);
});

// ---------- Retire / restore / graveyard ----------

it('retires (soft-deletes) a client and hides it from the index', function () {
    $client = Client::factory()->create(['name' => 'Sunset Group']);

    $this->actingAs($this->user)->delete("/clients/{$client->id}")->assertRedirect('/clients');

    expect($client->fresh()->retired_at)->not->toBeNull()
        ->and(Client::query()->where('id', $client->id)->exists())->toBeFalse()
        ->and(Client::withTrashed()->where('id', $client->id)->exists())->toBeTrue();
});

it('lists retired clients in the archive and restores them', function () {
    $client = Client::factory()->create(['name' => 'Old Harbor']);
    $client->delete();

    $this->actingAs($this->user)
        ->get('/clients/archive')
        ->assertInertia(fn ($page) => $page
            ->component('clients/archive')
            ->where('clients.data.0.name', 'Old Harbor'));

    $this->actingAs($this->user)
        ->patch("/clients/{$client->id}/restore")
        ->assertRedirect();

    expect($client->fresh()->retired_at)->toBeNull();
});

it('keeps retired clients off the regular index', function () {
    Client::factory()->create(['name' => 'Active One']);
    Client::factory()->create(['name' => 'Retired One'])->delete();

    $this->actingAs($this->user)
        ->get('/clients')
        ->assertInertia(fn ($page) => $page
            ->where('clients.data', fn ($rows) => collect($rows)->pluck('name')->contains('Active One')
                && ! collect($rows)->pluck('name')->contains('Retired One')));
});

// ---------- Contacts ----------

it('adds a contact and makes the first one primary automatically', function () {
    $client = Client::factory()->create();

    $this->actingAs($this->user)->post("/clients/{$client->id}/contacts", [
        'name' => 'First Person',
        'email' => 'first@x.test',
    ])->assertRedirect();

    $contact = $client->contacts()->sole();
    expect($contact->is_primary)->toBeTrue()
        ->and($client->fresh()->primary_contact_id)->toBe($contact->id);
});

it('promotes a different contact to primary', function () {
    $client = Client::factory()->create();
    $a = Contact::factory()->primary()->create(['client_id' => $client->id]);
    $b = Contact::factory()->create(['client_id' => $client->id, 'is_primary' => false]);
    $client->update(['primary_contact_id' => $a->id]);

    $this->actingAs($this->user)->put("/clients/{$client->id}/contacts/{$b->id}", [
        'name' => $b->name,
        'is_primary' => true,
    ])->assertRedirect();

    expect($b->fresh()->is_primary)->toBeTrue()
        ->and($a->fresh()->is_primary)->toBeFalse()
        ->and($client->fresh()->primary_contact_id)->toBe($b->id);
});

it('removes a contact and clears the primary pointer if it was primary', function () {
    $client = Client::factory()->create();
    $contact = Contact::factory()->primary()->create(['client_id' => $client->id]);
    $client->update(['primary_contact_id' => $contact->id]);

    $this->actingAs($this->user)
        ->delete("/clients/{$client->id}/contacts/{$contact->id}")
        ->assertRedirect();

    expect(Contact::query()->where('id', $contact->id)->exists())->toBeFalse()
        ->and($client->fresh()->primary_contact_id)->toBeNull();
});

it('rejects a contact from another client (scoping)', function () {
    $client = Client::factory()->create();
    $other = Client::factory()->create();
    $foreign = Contact::factory()->create(['client_id' => $other->id]);

    $this->actingAs($this->user)
        ->delete("/clients/{$client->id}/contacts/{$foreign->id}")
        ->assertNotFound();
});

it('validates contact email', function () {
    $client = Client::factory()->create();

    $this->actingAs($this->user)->post("/clients/{$client->id}/contacts", [
        'name' => 'Bad Email',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});

// ---------- Update (rich fields) ----------

it('updates address, tax id, and custom fields', function () {
    $client = Client::factory()->create();

    $this->actingAs($this->user)->put("/clients/{$client->id}", [
        'name' => $client->name,
        'type' => 'business',
        'tax_id' => '99-9999999',
        'address' => ['city' => 'Newtown', 'state' => 'FL'],
        'custom_fields' => [['key' => 'Tier', 'value' => 'Gold']],
    ])->assertRedirect();

    $fresh = $client->fresh();
    expect($fresh->tax_id_encrypted)->toBe('99-9999999')
        ->and($fresh->address_json['city'])->toBe('Newtown')
        ->and($fresh->custom_fields_json)->toBe(['Tier' => 'Gold']);
});
