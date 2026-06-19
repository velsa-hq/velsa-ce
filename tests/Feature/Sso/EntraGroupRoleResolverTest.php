<?php

use App\Models\EntraGroupRoleMapping;
use App\Models\User;
use App\Models\Venue;
use App\Services\Sso\EntraGroupRoleResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    $this->resolver = app(EntraGroupRoleResolver::class);
});

it('returns nothing when the user has no Entra groups', function () {
    $applied = $this->resolver->applyForUser($this->user, []);

    expect($applied)->toBe([]);
});

it('assigns the mapped role at a specific venue when venue_id is set', function () {
    $venue = Venue::factory()->create();
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-finance',
        'role_name' => 'finance',
        'venue_id' => $venue->id,
    ]);

    $applied = $this->resolver->applyForUser($this->user, ['group-finance']);

    expect($applied)->toHaveCount(1);
    expect($this->user->fresh()->roleAt($venue))->toBe('finance');
});

it('fans out a null-venue mapping to every active venue', function () {
    $a = Venue::factory()->create(['name' => 'A']);
    $b = Venue::factory()->create(['name' => 'B']);
    $c = Venue::factory()->create(['name' => 'C']);
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-readonly',
        'role_name' => 'read_only',
        'venue_id' => null,
    ]);

    $applied = $this->resolver->applyForUser($this->user, ['group-readonly']);

    expect($applied)->toHaveCount(3);
    foreach ([$a, $b, $c] as $venue) {
        expect($this->user->fresh()->roleAt($venue))->toBe('read_only');
    }
});

it('combines mappings from multiple groups the user belongs to', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-finance',
        'role_name' => 'finance',
        'venue_id' => $venueA->id,
    ]);
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-sales',
        'role_name' => 'sales_rep',
        'venue_id' => $venueB->id,
    ]);

    $this->resolver->applyForUser($this->user, ['group-finance', 'group-sales']);

    $user = $this->user->fresh();
    expect($user->roleAt($venueA))->toBe('finance')
        ->and($user->roleAt($venueB))->toBe('sales_rep');
});

it('skips mappings whose role was deleted from the system', function () {
    $venue = Venue::factory()->create();
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-orphan',
        'role_name' => 'long_gone_role',
        'venue_id' => $venue->id,
    ]);

    $applied = $this->resolver->applyForUser($this->user, ['group-orphan']);

    expect($applied)->toBe([]);
    expect($this->user->fresh()->roleAt($venue))->toBeNull();
});

it('ignores groups that have no mappings on file', function () {
    $venue = Venue::factory()->create();
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'group-known',
        'role_name' => 'finance',
        'venue_id' => $venue->id,
    ]);

    $applied = $this->resolver
        ->applyForUser($this->user, ['group-known', 'group-unrelated', 'group-other']);

    expect($applied)->toHaveCount(1);
});
