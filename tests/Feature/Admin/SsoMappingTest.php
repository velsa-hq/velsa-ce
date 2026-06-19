<?php

use App\Models\AuditEvent;
use App\Models\EntraGroupRoleMapping;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin(User::factory()->create());
});

it('lists all mappings on the index page', function () {
    $venue = Venue::factory()->create(['name' => 'Civic Center']);
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'group_label' => 'County Finance Team',
        'role_name' => 'finance',
        'venue_id' => null,
        'created_by_user_id' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/sso-mappings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/sso-mappings/index')
        ->has('mappings', 1)
        ->where('mappings.0.group_label', 'County Finance Team'));
});

it('creates a mapping with a valid group + role + venue', function () {
    $venue = Venue::factory()->create();

    $this->actingAs($this->admin)->post('/admin/sso-mappings', [
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'group_label' => 'Test',
        'role_name' => 'finance',
        'venue_id' => $venue->id,
    ])->assertRedirect();

    expect(EntraGroupRoleMapping::query()->count())->toBe(1);
    expect(AuditEvent::query()->where('event_type', 'sso_mapping.created')->count())->toBe(1);
});

it('accepts a null venue (means every active venue)', function () {
    $this->actingAs($this->admin)->post('/admin/sso-mappings', [
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
    ])->assertRedirect();

    expect(EntraGroupRoleMapping::query()->whereNull('venue_id')->count())->toBe(1);
});

it('rejects mappings with an invalid group id shape', function () {
    $this->actingAs($this->admin)->post('/admin/sso-mappings', [
        'entra_group_id' => 'short',
        'role_name' => 'finance',
    ])->assertSessionHasErrors('entra_group_id');
});

it('rejects mappings with an unknown role', function () {
    $this->actingAs($this->admin)->post('/admin/sso-mappings', [
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'not_a_role',
    ])->assertSessionHasErrors('role_name');
});

it('refuses to create a duplicate (group, role, venue) mapping', function () {
    EntraGroupRoleMapping::create([
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
        'venue_id' => null,
    ]);

    $this->actingAs($this->admin)->post('/admin/sso-mappings', [
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
    ])->assertStatus(422);

    expect(EntraGroupRoleMapping::query()->count())->toBe(1);
});

it('updates a mapping and writes an audit row', function () {
    $mapping = EntraGroupRoleMapping::create([
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
        'venue_id' => null,
    ]);

    $this->actingAs($this->admin)->put("/admin/sso-mappings/{$mapping->id}", [
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'sales_rep',
        'group_label' => 'Renamed',
    ])->assertRedirect();

    expect($mapping->fresh()->role_name)->toBe('sales_rep')
        ->and($mapping->fresh()->group_label)->toBe('Renamed');
    expect(AuditEvent::query()->where('event_type', 'sso_mapping.updated')->count())->toBe(1);
});

it('deletes a mapping and writes an audit row', function () {
    $mapping = EntraGroupRoleMapping::create([
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
        'venue_id' => null,
    ]);

    $this->actingAs($this->admin)
        ->delete("/admin/sso-mappings/{$mapping->id}")
        ->assertRedirect();

    expect(EntraGroupRoleMapping::query()->count())->toBe(0);
    expect(AuditEvent::query()->where('event_type', 'sso_mapping.deleted')->count())->toBe(1);
});

it('requires authentication on every endpoint', function () {
    $mapping = EntraGroupRoleMapping::create([
        'entra_group_id' => 'abc12345-aaaa-bbbb-cccc-ddddeeeeffff',
        'role_name' => 'finance',
    ]);

    $this->get('/admin/sso-mappings')->assertRedirect(route('login'));
    $this->get('/admin/sso-mappings/create')->assertRedirect(route('login'));
    $this->post('/admin/sso-mappings', [])->assertRedirect(route('login'));
    $this->get("/admin/sso-mappings/{$mapping->id}")->assertRedirect(route('login'));
    $this->put("/admin/sso-mappings/{$mapping->id}", [])->assertRedirect(route('login'));
    $this->delete("/admin/sso-mappings/{$mapping->id}")->assertRedirect(route('login'));
});
