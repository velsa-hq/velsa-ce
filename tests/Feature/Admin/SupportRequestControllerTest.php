<?php

use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists support requests for an admin', function () {
    $admin = grantSuperAdmin();
    SupportRequest::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/admin/support-requests')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/support-requests/index')
            ->has('requests.data', 3)
            ->where('open_count', 3)
        );
});

it('filters by status', function () {
    $admin = grantSuperAdmin();
    SupportRequest::factory()->create();
    SupportRequest::factory()->closed()->create();

    $this->actingAs($admin)
        ->get('/admin/support-requests?status=closed')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('requests.data', 1));
});

it('closes a request and records the resolver', function () {
    $admin = grantSuperAdmin();
    $request = SupportRequest::factory()->create();

    $this->actingAs($admin)
        ->put("/admin/support-requests/{$request->id}", ['status' => 'closed'])
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(SupportRequestStatus::Closed);
    expect($request->resolved_at)->not->toBeNull();
    expect($request->resolved_by)->toBe($admin->id);
});

it('reopens a closed request and clears the resolution', function () {
    $admin = grantSuperAdmin();
    $request = SupportRequest::factory()->closed()->create();

    $this->actingAs($admin)
        ->put("/admin/support-requests/{$request->id}", ['status' => 'open'])
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe(SupportRequestStatus::Open);
    expect($request->resolved_at)->toBeNull();
    expect($request->resolved_by)->toBeNull();
});

it('forbids a non-admin from the support-requests admin', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/support-requests')->assertForbidden();
});
