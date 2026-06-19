<?php

use App\Models\User;
use App\Models\Venue;
use App\Services\WorkOrders\WorkOrderAssigneeResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->resolver = app(WorkOrderAssigneeResolver::class);
});

it('returns null for a blank role', function () {
    expect($this->resolver->resolve(null))->toBeNull();
    expect($this->resolver->resolve(''))->toBeNull();
});

it('returns null when no user holds the role', function () {
    expect($this->resolver->resolve('ops_lead'))->toBeNull();
});

it('prefers a venue-scoped holder of the role', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userA->assignRoleAt($venueA, 'ops_lead');
    $userB->assignRoleAt($venueB, 'ops_lead');

    expect($this->resolver->resolve('ops_lead', $venueA->id))->toBe($userA->id);
    expect($this->resolver->resolve('ops_lead', $venueB->id))->toBe($userB->id);
});

it('falls back to any holder when none at the requested venue', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    $user = User::factory()->create();
    $user->assignRoleAt($venueA, 'ops_lead');

    expect($this->resolver->resolve('ops_lead', $venueB->id))->toBe($user->id);
});
