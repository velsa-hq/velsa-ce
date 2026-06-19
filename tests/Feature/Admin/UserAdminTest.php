<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin(User::factory()->create());
});

it('creates a local user, forces a password change, and assigns the initial role', function () {
    $venue = Venue::factory()->create(['name' => 'Civic Center']);

    $this->actingAs($this->admin)
        ->post('/admin/users', [
            'name' => 'New Hire',
            'email' => 'newhire@example.test',
            'password' => 'Sup3r-Str0ng-Pass!',
            'venue_id' => $venue->id,
            'role' => 'sales_rep',
        ])->assertRedirect();

    $user = User::query()->where('email', 'newhire@example.test')->sole();
    expect($user->force_password_change)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('Sup3r-Str0ng-Pass!'); // hashed at rest
    expect(DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->where('venue_id', $venue->id)
        ->exists())->toBeTrue();
    expect(AuditEvent::query()->where('event_type', 'user.created_by_admin')->count())->toBe(1);
});

it('requires a role when an initial venue is given', function () {
    $venue = Venue::factory()->create();

    $this->actingAs($this->admin)
        ->post('/admin/users', [
            'name' => 'No Role',
            'email' => 'norole@example.test',
            'password' => 'Sup3r-Str0ng-Pass!',
            'venue_id' => $venue->id,
        ])->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'norole@example.test')->exists())->toBeFalse();
});

it('renders the user detail page with assignments + role/venue catalogs', function () {
    $target = User::factory()->create(['name' => 'Casey User', 'email' => 'casey@example.test']);
    $venue = Venue::factory()->create(['name' => 'Civic Center']);
    $target->assignRoleAt($venue, 'sales_rep');

    $response = $this->actingAs($this->admin)->get("/admin/users/{$target->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/users/show')
        ->where('user.id', $target->id)
        ->where('user.name', 'Casey User')
        ->has('user.assignments', 1)
        ->where('user.assignments.0.role', 'sales_rep')
        ->where('user.assignments.0.venue_name', 'Civic Center')
        ->has('roles')
        ->has('venues'));
});

it('updates the name and email and writes an audit row', function () {
    $target = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test']);

    $this->actingAs($this->admin)
        ->put("/admin/users/{$target->id}", [
            'name' => 'New Name',
            'email' => 'new@example.test',
        ])->assertRedirect();

    expect($target->fresh()->name)->toBe('New Name')
        ->and($target->fresh()->email)->toBe('new@example.test');
    expect(AuditEvent::query()->where('event_type', 'user.profile_edited')->count())->toBe(1);
});

it('rejects an email that collides with another user', function () {
    User::factory()->create(['email' => 'taken@example.test']);
    $target = User::factory()->create(['email' => 'mine@example.test']);

    $this->actingAs($this->admin)
        ->put("/admin/users/{$target->id}", [
            'name' => $target->name,
            'email' => 'taken@example.test',
        ])->assertSessionHasErrors('email');
});

it('disables an account with a required reason', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/disable", ['reason' => 'left org'])
        ->assertRedirect();

    expect($target->fresh()->disabled_reason)->toBe('left org')
        ->and($target->fresh()->isDisabled())->toBeTrue()
        ->and($target->fresh()->force_logout_at)->not->toBeNull(); // session revoked
    expect(AuditEvent::query()->where('event_type', 'user.disabled')->count())->toBe(1);
});

it('refuses to disable without a reason', function () {
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/disable", [])
        ->assertSessionHasErrors('reason');

    expect($target->fresh()->isDisabled())->toBeFalse();
});

it('re-enables a disabled account', function () {
    $target = User::factory()->create(['disabled_reason' => 'temp lockout']);

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/enable")
        ->assertRedirect();

    expect($target->fresh()->isDisabled())->toBeFalse();
    expect(AuditEvent::query()->where('event_type', 'user.enabled')->count())->toBe(1);
});

it('assigns a venue x role pair and writes an audit row', function () {
    $target = User::factory()->create();
    $venue = Venue::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id,
            'role' => 'sales_rep',
        ])->assertRedirect();

    expect($target->fresh()->roleAt($venue))->toBe('sales_rep');
    expect(AuditEvent::query()->where('event_type', 'user.role_assigned')->count())->toBe(1);
});

it('refuses to assign an unknown role', function () {
    $target = User::factory()->create();
    $venue = Venue::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id,
            'role' => 'not_a_real_role',
        ])->assertSessionHasErrors('role');
});

it('removes a venue x role assignment', function () {
    $target = User::factory()->create();
    $venue = Venue::factory()->create();
    $target->assignRoleAt($venue, 'sales_rep');

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id,
            'role' => 'sales_rep',
        ])->assertRedirect();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($target->fresh()->roleAt($venue))->toBeNull();
    expect(AuditEvent::query()->where('event_type', 'user.role_unassigned')->count())->toBe(1);
});

it('requires authentication on every admin endpoint', function () {
    $target = User::factory()->create();

    $this->get("/admin/users/{$target->id}")->assertRedirect(route('login'));
    $this->put("/admin/users/{$target->id}", [])->assertRedirect(route('login'));
    $this->post("/admin/users/{$target->id}/disable", [])->assertRedirect(route('login'));
    $this->post("/admin/users/{$target->id}/enable")->assertRedirect(route('login'));
    $this->post("/admin/users/{$target->id}/assignments", [])->assertRedirect(route('login'));
    $this->delete("/admin/users/{$target->id}/assignments", [])->assertRedirect(route('login'));
});

it('renders the create-user page for an admin', function () {
    $this->withoutExceptionHandling();

    $this->actingAs($this->admin)->get('/admin/users/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/create')
            ->has('roles')
            ->has('venues'));
});

it('forbids a non-super-admin from granting super_admin on create (privesc guard)', function () {
    $countyAdmin = User::factory()->create();
    $countyAdmin->assignRoleAt(Venue::factory()->create(), 'org_admin'); // holds users.manage
    $venue = Venue::factory()->create();

    $this->actingAs($countyAdmin)
        ->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'password' => 'Sup3r-Str0ng-Pass!',
            'venue_id' => $venue->id, 'role' => 'super_admin',
        ])->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'sneaky@example.test')->exists())->toBeFalse();
});

it('forbids granting a role whose permissions exceed the actor\'s own (privesc ceiling)', function () {
    $venue = Venue::factory()->create();

    // actor holds only users.manage
    $limited = Role::create(['name' => 'limited_admin', 'guard_name' => 'web']);
    $limited->givePermissionTo('users.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->assignRoleAt($venue, 'limited_admin');
    $target = User::factory()->create();

    // org_admin carries accounting.post_journal / payments.refund the actor lacks
    $this->actingAs($actor)
        ->from("/admin/users/{$target->id}")
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id, 'role' => 'org_admin',
        ])
        ->assertSessionHasErrors('role');

    expect($target->fresh()->roleAt($venue))->toBeNull();
});

it('allows granting a role within the actor\'s own permissions', function () {
    $venue = Venue::factory()->create();

    $limited = Role::create(['name' => 'limited_admin', 'guard_name' => 'web']);
    $limited->givePermissionTo('users.manage', 'users.view');
    $subset = Role::create(['name' => 'viewer_only', 'guard_name' => 'web']);
    $subset->givePermissionTo('users.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $actor = User::factory()->create();
    $actor->assignRoleAt($venue, 'limited_admin');
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id, 'role' => 'viewer_only',
        ]);

    expect($target->fresh()->roleAt($venue))->toBe('viewer_only');
});

it('forbids a non-super-admin from assigning super_admin (privesc guard)', function () {
    $countyAdmin = User::factory()->create();
    $countyAdmin->assignRoleAt(Venue::factory()->create(), 'org_admin');
    $target = User::factory()->create();
    $venue = Venue::factory()->create();

    $this->actingAs($countyAdmin)
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => $venue->id, 'role' => 'super_admin',
        ])->assertSessionHasErrors('role');

    expect($target->fresh()->roleAt($venue))->toBeNull();
});

it('lets a super-admin grant super_admin', function () {
    $venue = Venue::factory()->create();

    $this->actingAs($this->admin) // super_admin
        ->post('/admin/users', [
            'name' => 'New Super', 'email' => 'newsuper@example.test', 'password' => 'Sup3r-Str0ng-Pass!',
            'venue_id' => $venue->id, 'role' => 'super_admin',
        ])->assertRedirect();

    expect(User::query()->where('email', 'newsuper@example.test')->sole()->roleAt($venue))->toBe('super_admin');
});

it('forbids a roleless authenticated user from POSTing to create a user', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/users', [
            'name' => 'X', 'email' => 'x@example.test', 'password' => 'Sup3r-Str0ng-Pass!',
        ])->assertForbidden();

    expect(User::query()->where('email', 'x@example.test')->exists())->toBeFalse();
});

it('fans a role out to every active venue when the venue is "all"', function () {
    $venues = Venue::factory()->count(3)->create();
    $target = User::factory()->create();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/assignments", [
            'venue_id' => 'all',
            'role' => 'sales_rep',
        ])->assertRedirect();

    foreach ($venues as $v) {
        expect($target->fresh()->roleAt($v))->toBe('sales_rep');
    }
});

it('removes a role from every venue when unassigning "all"', function () {
    $venues = Venue::factory()->count(3)->create();
    $target = User::factory()->create();
    foreach ($venues as $v) {
        $target->assignRoleAt($v, 'sales_rep');
    }

    $this->actingAs($this->admin)
        ->delete("/admin/users/{$target->id}/assignments", [
            'venue_id' => 'all',
            'role' => 'sales_rep',
        ])->assertRedirect();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($venues as $v) {
        expect($target->fresh()->roleAt($v))->toBeNull();
    }
});

it('hides super_admin from the grantable role list for a non-super-admin', function () {
    Venue::factory()->create();
    $orgAdmin = User::factory()->create();
    $orgAdmin->assignRoleAt(Venue::factory()->create(), 'org_admin'); // users.manage, not super_admin

    $this->actingAs($orgAdmin)->get('/admin/users/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => ! collect($roles)->contains('super_admin')));

    // super_admin still sees it
    $this->actingAs($this->admin)->get('/admin/users/create')
        ->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => collect($roles)->contains('super_admin')));
});
