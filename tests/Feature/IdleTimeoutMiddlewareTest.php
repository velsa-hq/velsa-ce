<?php

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('records a session.timeout audit event on idle logout', function () {
    config()->set('auth.idle_timeout_minutes', 15);
    $user = User::factory()->create([
        'last_active_at' => now()->subMinutes(30),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

    $event = AuditEvent::where('event_type', 'session.timeout')->where('user_id', $user->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->payload_json['reason'] ?? null)->toBe('idle_timeout');
});

it('lets an active user through the dashboard', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subMinutes(2),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('logs out a user whose last_active_at exceeds the timeout', function () {
    config()->set('auth.idle_timeout_minutes', 15);

    $user = User::factory()->create([
        'last_active_at' => now()->subMinutes(30),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

it('logs out a user whose force_logout_at has elapsed', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subMinute(),
        'force_logout_at' => now()->subMinute(),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertRedirect(route('login'));
    expect(Auth::check())->toBeFalse();
});

it('bumps last_active_at on each request after the coalescing window', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subMinutes(2),
        'email_verified_at' => now(),
    ]);

    $beforeTimestamp = $user->last_active_at;

    $this->actingAs($user)->get('/dashboard')->assertOk();

    expect($user->fresh()->last_active_at->greaterThan($beforeTimestamp))->toBeTrue();
});

it('does not write the last_active_at column inside the coalescing window', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subSeconds(10),
        'email_verified_at' => now(),
    ])->fresh(); // refresh so the timestamp matches db second-precision

    $before = $user->last_active_at;

    $this->actingAs($user)->get('/dashboard')->assertOk();

    // within the 60s window the write is skipped, db timestamp unchanged
    expect($user->fresh()->last_active_at->equalTo($before))->toBeTrue();
});

it('uses a configurable timeout from auth.idle_timeout_minutes', function () {
    config()->set('auth.idle_timeout_minutes', 60);

    $user = User::factory()->create([
        'last_active_at' => now()->subMinutes(30),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)->get('/dashboard')->assertOk();
    expect(Auth::check())->toBeTrue();
});
