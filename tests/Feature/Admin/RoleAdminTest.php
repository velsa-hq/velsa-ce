<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin(User::factory()->create());
});

it('lists every role with its permission and user counts', function () {
    $response = $this->actingAs($this->admin)->get('/admin/roles');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/roles/index')
        ->has('roles', count(RolesAndPermissionsSeeder::ROLES))
        ->where('roles.0.is_built_in', true));
});

it('renders the create page with the grouped permission catalog', function () {
    $response = $this->actingAs($this->admin)->get('/admin/roles/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/roles/form')
        ->where('mode', 'create')
        ->where('role.is_built_in', false)
        ->has('permission_groups'));
});

it('creates a new custom role with the chosen permissions', function () {
    $this->actingAs($this->admin)->post('/admin/roles', [
        'name' => 'venue_finance_admin',
        'permissions' => ['accounting.view', 'reports.view'],
    ])->assertRedirect();

    $role = Role::query()->where('name', 'venue_finance_admin')->first();
    expect($role)->not->toBeNull()
        ->and($role->permissions->pluck('name')->all())
        ->toBe(['accounting.view', 'reports.view']);
    expect(AuditEvent::query()->where('event_type', 'role.created')->count())->toBe(1);
});

it('refuses to create a role whose name collides with a built-in', function () {
    $this->actingAs($this->admin)->post('/admin/roles', [
        'name' => 'sales_rep',
        'permissions' => [],
    ])->assertSessionHasErrors('name');
});

it('refuses to create a role with an unknown permission', function () {
    $this->actingAs($this->admin)->post('/admin/roles', [
        'name' => 'custom_role',
        'permissions' => ['not.a.real.permission'],
    ])->assertSessionHasErrors('permissions.0');
});

it('rejects names that are not snake_case', function () {
    $this->actingAs($this->admin)->post('/admin/roles', [
        'name' => 'Bad-Name',
        'permissions' => [],
    ])->assertSessionHasErrors('name');
});

it('updates a custom role and writes an audit row', function () {
    $role = Role::create(['name' => 'custom_role']);

    $this->actingAs($this->admin)->put("/admin/roles/{$role->id}", [
        'name' => 'custom_role',
        'permissions' => ['venues.view', 'bookings.view'],
    ])->assertRedirect();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['bookings.view', 'venues.view']);
    expect(AuditEvent::query()->where('event_type', 'role.updated')->count())->toBe(1);
});

it('refuses to update a built-in role', function () {
    $builtIn = Role::query()->where('name', 'sales_rep')->first();

    $this->actingAs($this->admin)->put("/admin/roles/{$builtIn->id}", [
        'name' => 'sales_rep',
        'permissions' => [],
    ])->assertForbidden();
});

it('deletes a custom role with no assignments', function () {
    $role = Role::create(['name' => 'custom_throwaway']);

    $this->actingAs($this->admin)->delete("/admin/roles/{$role->id}")->assertRedirect();

    expect(Role::query()->where('name', 'custom_throwaway')->exists())->toBeFalse();
    expect(AuditEvent::query()->where('event_type', 'role.deleted')->count())->toBe(1);
});

it('refuses to delete a built-in role', function () {
    $builtIn = Role::query()->where('name', 'sales_rep')->first();

    $this->actingAs($this->admin)->delete("/admin/roles/{$builtIn->id}")->assertForbidden();
    expect(Role::query()->where('name', 'sales_rep')->exists())->toBeTrue();
});

it('refuses to delete a custom role that still has assignments', function () {
    $role = Role::create(['name' => 'custom_assigned']);
    $venue = Venue::factory()->create();
    $target = User::factory()->create();
    $target->assignRoleAt($venue, 'custom_assigned');

    $this->actingAs($this->admin)
        ->delete("/admin/roles/{$role->id}")
        ->assertSessionHasErrors('role');

    expect(Role::query()->where('name', 'custom_assigned')->exists())->toBeTrue();
});

it('requires authentication on every admin role endpoint', function () {
    $role = Role::query()->where('name', 'sales_rep')->first();

    $this->get('/admin/roles')->assertRedirect(route('login'));
    $this->get('/admin/roles/create')->assertRedirect(route('login'));
    $this->post('/admin/roles', [])->assertRedirect(route('login'));
    $this->get("/admin/roles/{$role->id}")->assertRedirect(route('login'));
    $this->put("/admin/roles/{$role->id}", [])->assertRedirect(route('login'));
    $this->delete("/admin/roles/{$role->id}")->assertRedirect(route('login'));
});
