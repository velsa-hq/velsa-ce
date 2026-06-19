<?php

use App\Models\AuditEvent;
use App\Models\AuditRule;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin();
});

it('creates an audit rule', function () {
    $this->actingAs($this->admin)
        ->post('/admin/audit-rules', ['name' => 'Privilege changes', 'event_type' => 'role.', 'is_active' => true])
        ->assertRedirect();

    $this->assertDatabaseHas('audit_rules', ['event_type' => 'role.', 'is_active' => true]);
});

it('toggles and deletes a rule', function () {
    $rule = AuditRule::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->put("/admin/audit-rules/{$rule->id}", ['name' => $rule->name, 'event_type' => $rule->event_type, 'is_active' => false])
        ->assertRedirect();
    expect($rule->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->delete("/admin/audit-rules/{$rule->id}")->assertRedirect();
    $this->assertDatabaseMissing('audit_rules', ['id' => $rule->id]);
});

it('flags audit events that match an active rule', function () {
    AuditRule::factory()->create(['event_type' => 'role.', 'is_active' => true]);
    AuditEvent::create(['event_type' => 'role.assigned']);
    AuditEvent::create(['event_type' => 'session.login']);

    $this->actingAs($this->admin)
        ->get('/admin/audit')
        ->assertInertia(fn (Assert $p) => $p
            ->where('events.data', fn ($rows) => collect($rows)->firstWhere('event_type', 'role.assigned')['flagged'] === true
                && collect($rows)->firstWhere('event_type', 'session.login')['flagged'] === false)
            ->etc()
        );
});

it('filters to flagged events only', function () {
    AuditRule::factory()->create(['event_type' => 'role.', 'is_active' => true]);
    AuditEvent::create(['event_type' => 'role.assigned']);
    AuditEvent::create(['event_type' => 'session.login']);

    $this->actingAs($this->admin)
        ->get('/admin/audit?flagged=1')
        ->assertInertia(fn (Assert $p) => $p->where('events.meta.total', 1)->etc());
});

it('forbids a user without audit access from managing rules', function () {
    $user = User::factory()->create();
    $user->assignRoleAt(Venue::factory()->create(), 'sales_rep');

    $this->actingAs($user)->get('/admin/audit-rules')->assertForbidden();
});
