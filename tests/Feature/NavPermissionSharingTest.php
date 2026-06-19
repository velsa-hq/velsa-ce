<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// the sidebar hides nav items via the shared auth.permissions union, so it
// must reflect exactly what the user holds and nothing more
it('shares only the permissions a role holds', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRoleAt(Venue::factory()->create(), 'demo');

    $perms = $this->actingAs($user)->get('/dashboard')->viewData('page')['props']['auth']['permissions'];

    expect($perms)->toContain('compliance.view')
        ->and($perms)->not->toContain('system.settings')
        ->and($perms)->not->toContain('users.view')
        ->and($perms)->not->toContain('data.import');
});

it('shares the full permission set for super_admin', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $perms = $this->actingAs($admin)->get('/dashboard')->viewData('page')['props']['auth']['permissions'];

    expect($perms)->toContain('system.settings')
        ->and($perms)->toContain('users.view')
        ->and($perms)->toContain('accounting.view');
});
