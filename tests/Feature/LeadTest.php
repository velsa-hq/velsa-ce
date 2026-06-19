<?php

use App\Enums\LeadStage;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts stage to LeadStage enum', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create();

    expect($lead->stage)->toBe(LeadStage::Qualified);
});

it('computes weighted value as estimated x probability', function () {
    $lead = Lead::factory()->create([
        'estimated_value_cents' => 100_000_00,
        'probability' => 0.4,
    ]);

    expect($lead->weightedValueCents())->toBe(40_000_00);
});

it('sets closed_at when transitioning into Won', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create(['closed_at' => null]);

    $lead->update(['stage' => LeadStage::Won->value]);

    expect($lead->closed_at)->not->toBeNull();
});

it('sets closed_at when transitioning into Lost', function () {
    $lead = Lead::factory()->atStage(LeadStage::ProposalSent)->create(['closed_at' => null]);

    $lead->update(['stage' => LeadStage::Lost->value, 'lost_reason' => 'budget']);

    expect($lead->closed_at)->not->toBeNull();
});

it('clears closed_at when moving from a terminal stage back to an open one', function () {
    $lead = Lead::factory()->atStage(LeadStage::Won)->create();
    expect($lead->closed_at)->not->toBeNull(); // sanity

    $lead->update(['stage' => LeadStage::ContractSent->value]);

    expect($lead->closed_at)->toBeNull();
});

it('scopes ->open() to non-terminal stages', function () {
    Lead::factory()->atStage(LeadStage::New)->count(2)->create();
    Lead::factory()->atStage(LeadStage::Qualified)->count(3)->create();
    Lead::factory()->atStage(LeadStage::Won)->create();
    Lead::factory()->atStage(LeadStage::Lost)->create();

    expect(Lead::query()->open()->count())->toBe(5);
});

it('scopes ->atStage() to a single stage', function () {
    Lead::factory()->atStage(LeadStage::Qualified)->count(3)->create();
    Lead::factory()->atStage(LeadStage::Won)->create();

    expect(Lead::query()->atStage(LeadStage::Qualified)->count())->toBe(3);
});

it('exposes client, venue, and owner relationships', function () {
    $client = Client::factory()->create();
    $venue = Venue::factory()->create();
    $user = User::factory()->create();

    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'owner_user_id' => $user->id,
    ]);

    expect($lead->client->is($client))->toBeTrue()
        ->and($lead->venue->is($venue))->toBeTrue()
        ->and($lead->owner->is($user))->toBeTrue();
});

it('uses default probability that matches the stage definition', function () {
    foreach (LeadStage::cases() as $stage) {
        $lead = Lead::factory()->atStage($stage)->create();
        expect($lead->probability)->toBe($stage->defaultProbability());
    }
});
