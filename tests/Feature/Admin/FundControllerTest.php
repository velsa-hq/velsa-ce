<?php

use App\Models\User;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('renders the funds index for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/admin/funds');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/funds/index')
            ->has('funds', 3)
            ->has('types')
        );
});

it('redirects unauthenticated users to login', function () {
    $this->get('/admin/funds')->assertRedirect('/login');
});
