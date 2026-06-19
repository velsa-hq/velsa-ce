<?php

use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin(User::factory()->create());
});

it('flags the user for a password change, revokes sessions, and audits it', function () {
    $target = User::factory()->create(['force_password_change' => false]);

    $this->actingAs($this->admin)
        ->post("/admin/users/{$target->id}/require-password-change")
        ->assertRedirect();

    $target->refresh();
    expect($target->force_password_change)->toBeTrue()
        ->and($target->force_logout_at)->not->toBeNull(); // session revoked

    expect(
        AuditEvent::query()
            ->where('event_type', 'user.password_change_required')
            ->where('subject_id', $target->id)
            ->exists()
    )->toBeTrue();
});

it('requires authentication', function () {
    $target = User::factory()->create();

    $this->post("/admin/users/{$target->id}/require-password-change")
        ->assertRedirect(route('login'));
});
