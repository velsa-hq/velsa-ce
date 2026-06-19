<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin(User::factory()->create());
});

it('lists permissions grouped by module with role and user counts', function () {
    $response = $this->actingAs($this->admin)->get('/admin/permissions');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/permissions/index')
        ->has('groups')
        ->where(
            'groups.0.permissions.0.role_count',
            fn ($count) => $count >= 0,
        ));
});

it('shows which roles grant a permission and which users hold it', function () {
    $venue = Venue::factory()->create(['name' => 'Civic Center']);
    $target = User::factory()->create(['name' => 'Casey Cash']);
    $target->assignRoleAt($venue, 'finance');

    $response = $this->actingAs($this->admin)->get('/admin/permissions/payments.refund');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/permissions/show')
        ->where('permission.name', 'payments.refund')
        ->where(
            'permission.granted_by_roles',
            fn ($roles) => collect($roles)->contains('finance')
                && collect($roles)->contains('super_admin'),
        )
        // audit also lists the acting super_admin's own venue, so don't assume order
        ->where('assignments', fn ($assignments) => collect($assignments)->contains(
            fn ($a) => $a['venue_name'] === 'Civic Center'
                && collect($a['users'])->contains(
                    fn ($u) => $u['name'] === 'Casey Cash' && $u['via_role'] === 'finance',
                ),
        )));
});

it('404s on an unknown permission name', function () {
    $this->actingAs($this->admin)
        ->get('/admin/permissions/not.a.real.permission')
        ->assertNotFound();
});

it('renders the user effective-permission matrix', function () {
    $venueA = Venue::factory()->create(['name' => 'Civic Center']);
    $venueB = Venue::factory()->create(['name' => 'Park Pavilion']);
    $target = User::factory()->create();
    $target->assignRoleAt($venueA, 'finance');
    $target->assignRoleAt($venueB, 'read_only');

    $response = $this->actingAs($this->admin)->get("/admin/users/{$target->id}/permissions");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/users/permissions')
        ->where('user.id', $target->id)
        ->has('venues', 2)
        ->has('permissions')
        ->where(
            'permissions',
            // granted at venueA (finance) but not venueB (read_only)
            function ($perms) use ($venueA, $venueB) {
                foreach ($perms as $p) {
                    if ($p['name'] === 'payments.refund') {
                        return $p['granted'][(string) $venueA->id] === true
                            && $p['granted'][(string) $venueB->id] === false;
                    }
                }

                return false;
            },
        ));
});

it('renders the matrix even when the user has no role anywhere', function () {
    $target = User::factory()->create();

    $response = $this->actingAs($this->admin)->get("/admin/users/{$target->id}/permissions");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('venues', [])
        ->where('roles_by_venue', []));
});

it('unions permissions across multiple roles at the same venue', function () {
    $venue = Venue::factory()->create();
    $target = User::factory()->create();
    // two roles at the same venue -> union of both grant lists
    $target->assignRoleAt($venue, 'sales_rep');
    $target->assignRoleAt($venue, 'finance');

    $response = $this->actingAs($this->admin)->get("/admin/users/{$target->id}/permissions");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('roles_by_venue.'.$venue->id, ['sales_rep', 'finance'])
        ->where('permissions', function ($perms) use ($venue) {
            $byName = collect($perms)->keyBy('name');

            // sales_rep grants leads.manage, finance grants payments.refund
            return $byName['leads.manage']['granted'][(string) $venue->id] === true
                && $byName['payments.refund']['granted'][(string) $venue->id] === true;
        }));
});

it('requires authentication on every audit endpoint', function () {
    $target = User::factory()->create();

    $this->get('/admin/permissions')->assertRedirect(route('login'));
    $this->get('/admin/permissions/payments.refund')->assertRedirect(route('login'));
    $this->get("/admin/users/{$target->id}/permissions")->assertRedirect(route('login'));
});
