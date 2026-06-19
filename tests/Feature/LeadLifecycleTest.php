<?php

use App\Enums\LeadStage;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
});

it('creates a new opportunity at an open stage with the default probability', function () {
    $client = Client::factory()->create();
    $venue = Venue::factory()->create();

    $this->actingAs($this->user)->post('/leads', [
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'name' => 'Conservation Gala 2027',
        'stage' => 'qualified',
        'estimated_value_dollars' => '217692',
        'expected_close_date' => '2027-03-01',
        'source' => 'referral',
    ])->assertRedirect();

    $lead = Lead::query()->where('name', 'Conservation Gala 2027')->sole();

    expect($lead->stage)->toBe(LeadStage::Qualified)
        ->and($lead->probability)->toBe(LeadStage::Qualified->defaultProbability())
        ->and($lead->estimated_value_cents)->toBe(21769200)
        ->and($lead->owner_user_id)->toBe($this->user->id);
});

it('refuses to create an opportunity directly at a terminal stage', function () {
    $client = Client::factory()->create();

    $this->actingAs($this->user)->post('/leads', [
        'client_id' => $client->id,
        'name' => 'Sneaky Won',
        'stage' => 'won',
    ])->assertSessionHasErrors('stage');
});

it('clones an opportunity into a fresh New lead', function () {
    $lead = Lead::factory()->atStage(LeadStage::Lost)->create([
        'name' => 'Spring Expo',
        'estimated_value_cents' => 5000000,
    ]);

    $this->actingAs($this->user)
        ->post("/leads/{$lead->id}/clone")
        ->assertRedirect();

    $copy = Lead::query()->where('name', 'Spring Expo (copy)')->sole();

    expect($copy->stage)->toBe(LeadStage::New)
        ->and($copy->estimated_value_cents)->toBe(5000000)
        ->and($copy->expected_close_date)->toBeNull()
        ->and($copy->lost_reason)->toBeNull()
        ->and($copy->owner_user_id)->toBe($this->user->id);
});

it('reopens a closed lead back into the funnel at Qualified', function () {
    $lead = Lead::factory()->atStage(LeadStage::Lost)->create([
        'lost_reason' => 'budget',
        'archived_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/reopen")
        ->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->stage)->toBe(LeadStage::Qualified)
        ->and($fresh->lost_reason)->toBeNull()
        ->and($fresh->closed_at)->toBeNull()
        ->and($fresh->archived_at)->toBeNull();
});

it('refuses to reopen a lead that already converted to a booking', function () {
    $booking = Booking::factory()->create();
    $lead = Lead::factory()->atStage(LeadStage::Won)->create([
        'converted_booking_id' => $booking->id,
        'converted_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/reopen")
        ->assertSessionHasErrors('stage');

    expect($lead->fresh()->stage)->toBe(LeadStage::Won);
});

it('refuses to reopen an open lead', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/reopen")
        ->assertSessionHasErrors('stage');
});

it('manually archives a closed lead', function () {
    $lead = Lead::factory()->atStage(LeadStage::Won)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/archive")
        ->assertRedirect();

    expect($lead->fresh()->archived_at)->not->toBeNull();
});

it('refuses to archive an open lead', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/archive")
        ->assertSessionHasErrors('archive');

    expect($lead->fresh()->archived_at)->toBeNull();
});

it('hides archived leads from the board but lists them in the archive', function () {
    Lead::factory()->atStage(LeadStage::Won)->create(['name' => 'Live deal']);
    Lead::factory()->atStage(LeadStage::Lost)->create([
        'name' => 'Old deal',
        'archived_at' => now(),
    ]);

    $boardNames = fn ($columns) => collect($columns)
        ->flatMap(fn ($c) => collect($c['leads'])->pluck('name'));

    $this->actingAs($this->user)
        ->get('/pipeline')
        ->assertInertia(fn ($page) => $page
            ->where('columns', fn ($columns) => $boardNames($columns)->contains('Live deal')
                && ! $boardNames($columns)->contains('Old deal')));

    $this->actingAs($this->user)
        ->get('/pipeline/archive')
        ->assertInertia(fn ($page) => $page
            ->has('leads', 1)
            ->where('leads.0.name', 'Old deal'));
});

it('searches the archive by lead name', function () {
    Lead::factory()->atStage(LeadStage::Lost)->create(['name' => 'Harvest Ball', 'archived_at' => now()]);
    Lead::factory()->atStage(LeadStage::Lost)->create(['name' => 'Tech Summit', 'archived_at' => now()]);

    $this->actingAs($this->user)
        ->get('/pipeline/archive?q=harvest')
        ->assertInertia(fn ($page) => $page
            ->has('leads', 1)
            ->where('leads.0.name', 'Harvest Ball'));
});
