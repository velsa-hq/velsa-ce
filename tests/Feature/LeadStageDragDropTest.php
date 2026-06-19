<?php

use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
});

it('moves a lead to a new open stage and updates probability to the default', function () {
    $lead = Lead::factory()->atStage(LeadStage::New)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/stage", ['stage' => 'qualified'])
        ->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->stage)->toBe(LeadStage::Qualified)
        ->and($fresh->probability)->toBe(LeadStage::Qualified->defaultProbability())
        ->and($fresh->closed_at)->toBeNull();
});

it('stamps closed_at when transitioning to Won', function () {
    $lead = Lead::factory()->atStage(LeadStage::ContractSent)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/stage", ['stage' => 'won'])
        ->assertRedirect();

    expect($lead->fresh()->closed_at)->not->toBeNull();
});

it('requires a reason when marking Lost', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/stage", ['stage' => 'lost'])
        ->assertSessionHasErrors('lost_reason');

    expect($lead->fresh()->stage)->toBe(LeadStage::Qualified);
});

it('saves the lost reason when one is supplied', function () {
    $lead = Lead::factory()->atStage(LeadStage::Qualified)->create();

    $this->actingAs($this->user)->patch("/leads/{$lead->id}/stage", [
        'stage' => 'lost',
        'lost_reason' => 'went with competitor',
    ])->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->stage)->toBe(LeadStage::Lost)
        ->and($fresh->lost_reason)->toBe('went with competitor')
        ->and($fresh->closed_at)->not->toBeNull();
});

it('clears the lost reason when moving back to an open stage', function () {
    $lead = Lead::factory()->atStage(LeadStage::Lost)->create([
        'lost_reason' => 'budget',
    ]);

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/stage", ['stage' => 'qualified'])
        ->assertRedirect();

    $fresh = $lead->fresh();
    expect($fresh->stage)->toBe(LeadStage::Qualified)
        ->and($fresh->lost_reason)->toBeNull();
});

it('rejects unknown stage values', function () {
    $lead = Lead::factory()->atStage(LeadStage::New)->create();

    $this->actingAs($this->user)
        ->patch("/leads/{$lead->id}/stage", ['stage' => 'not_a_stage'])
        ->assertSessionHasErrors('stage');
});

it('requires authentication', function () {
    $lead = Lead::factory()->atStage(LeadStage::New)->create();

    $this->patch("/leads/{$lead->id}/stage", ['stage' => 'qualified'])
        ->assertRedirect(route('login'));
});
