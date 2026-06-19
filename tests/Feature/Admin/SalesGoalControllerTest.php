<?php

use App\Models\SalesGoal;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin();
    $this->rep = User::factory()->create(['name' => 'Rep One']);
});

it('renders the sales-goals admin index', function () {
    $this->actingAs($this->admin)
        ->get('/admin/sales-goals')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/sales-goals/index')->has('salespeople'));
});

it('stores an annual goal (dollars -> cents)', function () {
    $this->actingAs($this->admin)
        ->post('/admin/sales-goals', ['user_id' => $this->rep->id, 'year' => 2026, 'month' => null, 'target_dollars' => '120000'])
        ->assertRedirect();

    $this->assertDatabaseHas('sales_goals', [
        'user_id' => $this->rep->id, 'year' => 2026, 'month' => null, 'target_cents' => 12_000_000,
    ]);
});

it('upserts the same period instead of duplicating', function () {
    $this->actingAs($this->admin)->post('/admin/sales-goals', ['user_id' => $this->rep->id, 'year' => 2026, 'target_dollars' => '100000']);
    $this->actingAs($this->admin)->post('/admin/sales-goals', ['user_id' => $this->rep->id, 'year' => 2026, 'target_dollars' => '150000']);

    expect(SalesGoal::query()->where('user_id', $this->rep->id)->where('year', 2026)->count())->toBe(1)
        ->and(SalesGoal::query()->where('user_id', $this->rep->id)->where('year', 2026)->value('target_cents'))->toBe(15_000_000);
});

it('deletes a goal', function () {
    $goal = SalesGoal::factory()->create(['user_id' => $this->rep->id]);

    $this->actingAs($this->admin)
        ->delete("/admin/sales-goals/{$goal->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('sales_goals', ['id' => $goal->id]);
});

it('forbids a salesperson without sales.manage_goals', function () {
    $user = User::factory()->create();
    $user->assignRoleAt(Venue::factory()->create(), 'sales_rep');

    $this->actingAs($user)->get('/admin/sales-goals')->assertForbidden();
});
