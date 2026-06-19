<?php

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['auth.inactivity_disable_days' => 35]));

it('disables an account inactive beyond the threshold', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subDays(40),
        'disabled_reason' => null,
    ]);

    $this->artisan('users:disable-inactive')->assertExitCode(0);

    $user->refresh();
    expect($user->disabled_reason)->toBe('auto:inactivity')
        ->and($user->isDisabled())->toBeTrue()
        ->and($user->force_logout_at)->not->toBeNull();

    expect(AuditEvent::where('event_type', 'user.disabled')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('leaves recently-active accounts enabled', function () {
    $user = User::factory()->create(['last_active_at' => now()->subDays(10), 'disabled_reason' => null]);

    $this->artisan('users:disable-inactive');

    expect($user->refresh()->disabled_reason)->toBeNull();
});

it('never re-touches an already-disabled account', function () {
    $user = User::factory()->create([
        'last_active_at' => now()->subDays(99),
        'disabled_reason' => 'manual: policy violation',
    ]);

    $this->artisan('users:disable-inactive');

    expect($user->refresh()->disabled_reason)->toBe('manual: policy violation');
    expect(AuditEvent::where('event_type', 'user.disabled')->where('subject_id', $user->id)->count())->toBe(0);
});

it('ignores accounts that have never been active', function () {
    $user = User::factory()->create(['last_active_at' => null, 'disabled_reason' => null]);

    $this->artisan('users:disable-inactive');

    expect($user->refresh()->disabled_reason)->toBeNull();
});

it('is a no-op when the threshold is zero', function () {
    config(['auth.inactivity_disable_days' => 0]);
    $user = User::factory()->create(['last_active_at' => now()->subDays(400), 'disabled_reason' => null]);

    $this->artisan('users:disable-inactive')->assertExitCode(0);

    expect($user->refresh()->disabled_reason)->toBeNull();
});
