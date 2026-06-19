<?php

use App\Enums\ClientType;
use App\Enums\LeadStage;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('casts type to ClientType enum', function () {
    $client = Client::factory()->create(['type' => ClientType::Government->value]);

    expect($client->type)->toBe(ClientType::Government);
});

it('encrypts tax_id at rest', function () {
    $client = Client::factory()->create(['tax_id_encrypted' => '12-3456789']);

    $rawDbValue = DB::table('clients')->where('id', $client->id)->value('tax_id_encrypted');

    expect($rawDbValue)->not->toBe('12-3456789')
        ->and($client->fresh()->tax_id_encrypted)->toBe('12-3456789');
});

it('exposes contacts, primaryContact, and leads relationships', function () {
    $client = Client::factory()->create();
    $primary = Contact::factory()->primary()->create(['client_id' => $client->id]);
    $client->update(['primary_contact_id' => $primary->id]);
    Contact::factory()->count(2)->create(['client_id' => $client->id]);
    Lead::factory()->count(3)->create(['client_id' => $client->id]);

    expect($client->contacts()->count())->toBe(3)
        ->and($client->primaryContact->is($primary))->toBeTrue()
        ->and($client->leads()->count())->toBe(3);
});

it('soft-deletes a client via retired_at', function () {
    $client = Client::factory()->create();

    $client->delete();

    expect(Client::find($client->id))->toBeNull()
        ->and($client->trashed())->toBeTrue();
});

it('hides tax_id_encrypted from JSON serialization', function () {
    $client = Client::factory()->create(['tax_id_encrypted' => '12-3456789']);

    expect($client->toArray())->not->toHaveKey('tax_id_encrypted');
});

it('counts only open leads toward open_pipeline_cents', function () {
    // regression: whereIn() on the enum-cast stage matched nothing, so
    // open_pipeline_cents was always 0; open = not won/lost
    $this->seed(RolesAndPermissionsSeeder::class);
    $venue = Venue::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRoleAt($venue, 'read_only'); // clients.view

    $client = Client::factory()->create();
    Lead::factory()->atStage(LeadStage::Qualified)->create([
        'client_id' => $client->id, 'estimated_value_cents' => 100_00, 'probability' => 0.5,
    ]); // open -> 50_00
    Lead::factory()->atStage(LeadStage::Won)->create([
        'client_id' => $client->id, 'estimated_value_cents' => 900_00, 'probability' => 1.0,
    ]); // closed -> excluded
    Lead::factory()->atStage(LeadStage::Lost)->create([
        'client_id' => $client->id, 'estimated_value_cents' => 900_00, 'probability' => 0.0,
    ]); // closed -> excluded

    $this->actingAs($user)->get('/clients')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('clients/index')
            ->where('clients.data.0.open_pipeline_cents', 50_00));
});
