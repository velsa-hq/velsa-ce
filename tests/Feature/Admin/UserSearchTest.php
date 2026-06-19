<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('filters the admin user list by name', function () {
    $admin = grantSuperAdmin();
    User::factory()->create(['name' => 'Samuel Parkington', 'email' => 'sp@example.gov']);
    User::factory()->create(['name' => 'Dana Rivers', 'email' => 'dana@example.gov']);

    $this->actingAs($admin)
        ->get('/admin/users?q=Parkington')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->where('filters.q', 'Parkington')
            ->has('users', 1)
            ->where('users.0.name', 'Samuel Parkington')
        );
});

it('filters by email too', function () {
    $admin = grantSuperAdmin();
    User::factory()->create(['name' => 'Sam Park', 'email' => 'sam.park@uniquetoken.test']);

    $this->actingAs($admin)
        ->get('/admin/users?q=uniquetoken.test')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('users', 1)->where('users.0.name', 'Sam Park'));
});

it('returns all users when no query is given', function () {
    $admin = grantSuperAdmin();
    User::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('filters.q', '')->has('users', 4)); // 3 + admin
});
