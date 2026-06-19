<?php

use App\Enums\ActivityKind;
use App\Enums\LeadStage;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the lead detail page with activities split by completion', function () {
    $user = grantSuperAdmin();
    $lead = Lead::factory()->create(['stage' => 'qualified']);

    Activity::factory()->create([
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'kind' => ActivityKind::Call->value,
        'summary' => 'Discovery call',
        'due_at' => now()->addDays(2),
        'completed_at' => null,
    ]);
    Activity::factory()->create([
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'kind' => ActivityKind::SiteVisit->value,
        'summary' => 'Tour Grand Ballroom',
        'due_at' => now()->subDays(3),
        'completed_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->get("/leads/{$lead->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/show')
            ->where('lead.id', $lead->id)
            ->where('lead.name', $lead->name)
            ->has('activities', 2)
        );
});

it('renders the edit page with stages and current values', function () {
    $user = grantSuperAdmin();
    $lead = Lead::factory()->create([
        'estimated_value_cents' => 350_000,
        'probability' => 0.5,
    ]);

    $this->actingAs($user)
        ->get("/leads/{$lead->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('leads/edit')
            ->where('lead.id', $lead->id)
            ->where('lead.estimated_value_dollars', '3500.00')
            ->where('stages', ['new', 'qualified', 'proposal_sent', 'contract_sent', 'won', 'lost'])
        );
});

it('updates the lead with dollar-to-cents conversion', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create();
    $venue = Venue::factory()->create();
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'venue_id' => $venue->id,
    ]);

    $response = $this->actingAs($user)->put("/leads/{$lead->id}", [
        'client_id' => $client->id,
        'venue_id' => $venue->id,
        'name' => 'Renamed lead',
        'stage' => 'qualified',
        'probability' => 0.4,
        'estimated_value_dollars' => '4250.75',
        'source' => 'referral',
    ]);

    $response->assertRedirect("/leads/{$lead->id}");

    $lead->refresh();
    expect($lead->name)->toBe('Renamed lead')
        ->and($lead->stage)->toBe(LeadStage::Qualified)
        ->and($lead->estimated_value_cents)->toBe(425075);
});

it('auto-stamps closed_at when moving the lead to a terminal stage', function () {
    $user = grantSuperAdmin();
    $client = Client::factory()->create();
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'stage' => 'qualified',
        'closed_at' => null,
    ]);

    $this->actingAs($user)->put("/leads/{$lead->id}", [
        'client_id' => $client->id,
        'name' => $lead->name,
        'stage' => 'won',
        'probability' => 1.0,
    ])->assertRedirect("/leads/{$lead->id}");

    expect($lead->fresh()->closed_at)->not->toBeNull();
});

it('creates an activity on a lead', function () {
    $user = grantSuperAdmin();
    $lead = Lead::factory()->create();

    $this->actingAs($user)->post("/leads/{$lead->id}/activities", [
        'kind' => 'meeting',
        'summary' => 'Walkthrough at venue',
        'due_at' => now()->addDays(5)->toDateTimeString(),
    ])->assertRedirect("/leads/{$lead->id}");

    expect(Activity::query()->where('subject_id', $lead->id)->where('summary', 'Walkthrough at venue')->exists())
        ->toBeTrue();
});

it('toggles an activity from open to complete and back', function () {
    $user = grantSuperAdmin();
    $lead = Lead::factory()->create();
    $activity = Activity::factory()->create([
        'subject_type' => Lead::class,
        'subject_id' => $lead->id,
        'completed_at' => null,
    ]);

    $this->actingAs($user)->patch("/leads/{$lead->id}/activities/{$activity->id}/toggle");

    expect($activity->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($user)->patch("/leads/{$lead->id}/activities/{$activity->id}/toggle");

    expect($activity->fresh()->completed_at)->toBeNull();
});

it('returns 404 when toggling an activity that does not belong to the lead', function () {
    $user = grantSuperAdmin();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();
    $activity = Activity::factory()->create([
        'subject_type' => Lead::class,
        'subject_id' => $leadB->id,
        'completed_at' => null,
    ]);

    $this->actingAs($user)
        ->patch("/leads/{$leadA->id}/activities/{$activity->id}/toggle")
        ->assertNotFound();
});
