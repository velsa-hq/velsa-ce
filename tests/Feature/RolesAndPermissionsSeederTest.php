<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('permission.testing', true);
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates the full permission catalog', function () {
    expect(Permission::query()->count())->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS));
});

it('creates every named role', function () {
    $expected = array_keys(RolesAndPermissionsSeeder::ROLES);

    expect(Role::query()->pluck('name')->all())
        ->toEqualCanonicalizing($expected);
});

it('grants super_admin the full permission catalog via wildcard', function () {
    $superAdmin = Role::findByName('super_admin');

    expect($superAdmin->permissions()->count())
        ->toBe(count(RolesAndPermissionsSeeder::PERMISSIONS));
});

it('grants venue_admin a curated subset', function () {
    $venueAdmin = Role::findByName('venue_admin');
    $grantNames = $venueAdmin->permissions()->pluck('name')->all();

    expect($grantNames)->toContain('venues.view', 'spaces.manage', 'bookings.approve')
        ->and($grantNames)->not->toContain('venues.manage', 'accounting.export_ledger');
});

it('grants exhibitor zero direct permissions', function () {
    $exhibitor = Role::findByName('exhibitor');

    expect($exhibitor->permissions()->count())->toBe(0);
});

it('does not duplicate grants when re-run', function () {
    $before = Role::findByName('org_admin')->permissions()->count();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName('org_admin')->permissions()->count())->toBe($before);
});
