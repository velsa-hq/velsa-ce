<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin();
});

it('renders the create form pre-filled from a cloned role', function () {
    $source = Role::findByName('org_admin');

    $this->actingAs($this->admin)
        ->get("/admin/roles/{$source->id}/clone")
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/roles/form')
            ->where('mode', 'create')
            ->where('role.name', 'org_admin_copy')
            ->where('cloned_from', 'org_admin')
            ->where('role.permissions', fn ($perms) => count($perms) > 0)
        );
});

it('creates and deletes a custom permission', function () {
    $this->actingAs($this->admin)->post('/admin/permissions', ['name' => 'exports.run'])->assertRedirect();
    $this->assertDatabaseHas('permissions', ['name' => 'exports.run']);

    $perm = Permission::findByName('exports.run');
    $this->actingAs($this->admin)->delete("/admin/permissions/{$perm->id}")->assertRedirect();
    $this->assertDatabaseMissing('permissions', ['name' => 'exports.run']);
});

it('refuses to delete a built-in permission', function () {
    $perm = Permission::findByName('bookings.view');

    $this->actingAs($this->admin)->delete("/admin/permissions/{$perm->id}")->assertForbidden();
    $this->assertDatabaseHas('permissions', ['name' => 'bookings.view']);
});

it('validates custom permission name format', function () {
    $this->actingAs($this->admin)->post('/admin/permissions', ['name' => 'Not Valid'])->assertSessionHasErrors('name');
});

it('stamps an expiry on a role assignment', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();
    $when = now()->addDays(7)->toDateString();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$user->id}/assignments", ['venue_id' => $venue->id, 'role' => 'sales_rep', 'expires_at' => $when])
        ->assertRedirect();

    $row = DB::table('model_has_roles')->where('model_id', $user->id)->where('venue_id', $venue->id)->first();
    expect($row->expires_at)->not->toBeNull();
});

it('roles:expire removes only past-due assignments', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();
    $user->assignRoleAt($venue, 'sales_rep');

    // force it expired
    DB::table('model_has_roles')->where('model_id', $user->id)->where('venue_id', $venue->id)
        ->update(['expires_at' => now()->subHour()]);

    $this->artisan('roles:expire')->assertSuccessful();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(0);
});

it('roles:expire keeps assignments that have not expired', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();
    $user->assignRoleAt($venue, 'sales_rep');
    DB::table('model_has_roles')->where('model_id', $user->id)->update(['expires_at' => now()->addDays(3)]);

    $this->artisan('roles:expire')->assertSuccessful();

    expect(DB::table('model_has_roles')->where('model_id', $user->id)->count())->toBe(1);
});
