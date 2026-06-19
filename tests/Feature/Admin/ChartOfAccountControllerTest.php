<?php

use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('renders the chart of accounts index for authenticated users', function () {
    $response = $this->actingAs($this->user)->get('/admin/chart-of-accounts');

    $response->assertStatus(200)
        ->assertInertia(fn ($p) => $p
            ->component('admin/chart-of-accounts/index')
            ->has('accounts')
            ->has('types')
        );
});

it('redirects unauthenticated users to login', function () {
    $this->get('/admin/chart-of-accounts')->assertRedirect('/login');
});
