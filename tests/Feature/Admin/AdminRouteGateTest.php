<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// verified user holding $role at a fresh venue (null = no role)
function userWith(?string $role): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);

    if ($role !== null) {
        $user->assignRoleAt(Venue::factory()->create(), $role);
    }

    return $user;
}

it('forbids a user with no role from every admin area', function () {
    $user = userWith(null);

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    $this->actingAs($user)->get('/admin/system-settings')->assertForbidden();
    $this->actingAs($user)->get('/admin/audit')->assertForbidden();
});

it('forbids a low-privilege role from the role/permission escalation surface', function () {
    // sales_rep holds no admin permissions
    $user = userWith('sales_rep');

    $this->actingAs($user)->get('/admin/roles')->assertForbidden();
    $this->actingAs($user)->get('/admin/users')->assertForbidden();
    $this->actingAs($user)->post('/admin/roles', ['name' => 'pwn', 'permissions' => []])->assertForbidden();
});

it('lets super_admin into every admin area', function () {
    $user = userWith('super_admin');

    $this->actingAs($user)->get('/admin/users')->assertOk();
    $this->actingAs($user)->get('/admin/roles')->assertOk();
    $this->actingAs($user)->get('/admin/system-settings')->assertOk();
    $this->actingAs($user)->get('/admin/audit')->assertOk();
});

it('scopes access to the permissions a role actually holds', function () {
    // finance holds audit.view + accounting.view, not users.view or system.settings
    $user = userWith('finance');

    $this->actingAs($user)->get('/admin/audit')->assertOk();              // audit.view
    $this->actingAs($user)->get('/admin/chart-of-accounts')->assertOk(); // accounting.view
    $this->actingAs($user)->get('/admin/users')->assertForbidden();      // no users.view
    $this->actingAs($user)->get('/admin/system-settings')->assertForbidden(); // no system.settings
});
