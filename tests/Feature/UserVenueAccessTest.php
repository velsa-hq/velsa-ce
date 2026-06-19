<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('permission.testing', true);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('assigns a venue-scoped role and reports it back', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();

    $user->assignRoleAt($venue, 'sales_rep');

    expect($user->roleAt($venue))->toBe('sales_rep');
});

it('lets a user hold different roles at different venues', function () {
    $user = User::factory()->create();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    $user->assignRoleAt($venueA, 'sales_rep');
    $user->assignRoleAt($venueB, 'read_only');

    expect($user->roleAt($venueA))->toBe('sales_rep')
        ->and($user->roleAt($venueB))->toBe('read_only');
});

it('grants permissions at one venue without leaking to another', function () {
    $user = User::factory()->create();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    $user->assignRoleAt($venueA, 'sales_rep');

    expect($user->canAt($venueA, 'bookings.create'))->toBeTrue()
        ->and($user->canAt($venueB, 'bookings.create'))->toBeFalse();
});

it('super_admin grants every permission catalogued', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();

    $user->assignRoleAt($venue, 'super_admin');

    foreach (RolesAndPermissionsSeeder::PERMISSIONS as $permission) {
        expect($user->canAt($venue, $permission))->toBeTrue();
    }
});

it('read_only never grants management permissions', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();

    $user->assignRoleAt($venue, 'read_only');

    expect($user->canAt($venue, 'venues.view'))->toBeTrue()
        ->and($user->canAt($venue, 'venues.manage'))->toBeFalse()
        ->and($user->canAt($venue, 'bookings.create'))->toBeFalse()
        ->and($user->canAt($venue, 'payments.process'))->toBeFalse();
});

it('revokes all roles at a venue without touching other venues', function () {
    $user = User::factory()->create();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    $user->assignRoleAt($venueA, 'sales_rep');
    $user->assignRoleAt($venueB, 'sales_rep');

    $user->revokeAllRolesAt($venueA);

    expect($user->roleAt($venueA))->toBeNull()
        ->and($user->roleAt($venueB))->toBe('sales_rep');
});

it('lists accessibleVenues where the user holds any role', function () {
    $user = User::factory()->create();
    $venueA = Venue::factory()->create(['name' => 'Alpha']);
    $venueB = Venue::factory()->create(['name' => 'Bravo']);
    Venue::factory()->create(['name' => 'Charlie']); // no role here

    $user->assignRoleAt($venueA, 'sales_rep');
    $user->assignRoleAt($venueB, 'read_only');

    expect($user->accessibleVenues()->pluck('name')->all())
        ->toBe(['Alpha', 'Bravo']);
});

it('restores the prior Spatie team id after a call', function () {
    $user = User::factory()->create();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($venueA->id);

    $user->assignRoleAt($venueB, 'sales_rep');

    expect($registrar->getPermissionsTeamId())->toBe($venueA->id);
});

it('flags a disabled user', function () {
    $user = User::factory()->create(['disabled_reason' => 'inactivity']);

    expect($user->isDisabled())->toBeTrue();
});

it('does not flag an active user as disabled', function () {
    $user = User::factory()->create();

    expect($user->isDisabled())->toBeFalse();
});
