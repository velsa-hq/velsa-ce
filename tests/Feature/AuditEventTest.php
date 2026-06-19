<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an audit row with relations', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();

    $event = AuditEvent::query()->create([
        'user_id' => $user->id,
        'venue_id' => $venue->id,
        'event_type' => 'session.login',
        'payload_json' => ['guard' => 'web'],
        'created_at' => now(),
    ]);

    expect($event->user->is($user))->toBeTrue()
        ->and($event->venue->is($venue))->toBeTrue()
        ->and($event->payload_json)->toBe(['guard' => 'web']);
});

it('blocks updates at the application layer', function () {
    $event = AuditEvent::query()->create([
        'event_type' => 'session.login',
        'created_at' => now(),
    ]);

    expect(fn () => $event->update(['event_type' => 'tampered']))
        ->toThrow(RuntimeException::class, 'append-only');
});

it('blocks deletes at the application layer', function () {
    $event = AuditEvent::query()->create([
        'event_type' => 'session.login',
        'created_at' => now(),
    ]);

    expect(fn () => $event->delete())
        ->toThrow(RuntimeException::class, 'append-only');
});

it('masks sensitive payload keys at any nesting depth', function () {
    $event = AuditEvent::query()->create([
        'event_type' => 'user.created',
        'payload_json' => [
            'email' => 'a@b.com',
            'password' => 'plaintext',
            'profile' => [
                'tax_id' => '123-45-6789',
                'two_factor_secret' => 'TOTPSECRET',
                'name' => 'Erik',
            ],
        ],
        'created_at' => now(),
    ]);

    $masked = $event->maskedPayload();

    expect($masked['email'])->toBe('a@b.com')
        ->and($masked['password'])->toBe('***REDACTED***')
        ->and($masked['profile']['tax_id'])->toBe('***REDACTED***')
        ->and($masked['profile']['two_factor_secret'])->toBe('***REDACTED***')
        ->and($masked['profile']['name'])->toBe('Erik');
});

it('returns an empty masked payload when payload_json is null', function () {
    $event = AuditEvent::query()->create([
        'event_type' => 'session.logout',
        'created_at' => now(),
    ]);

    expect($event->maskedPayload())->toBe([]);
});

it('retains venue_id after the venue is deleted (immutable audit)', function () {
    $venue = Venue::factory()->create();
    $event = AuditEvent::query()->create([
        'venue_id' => $venue->id,
        'event_type' => 'venue.deleted',
        'created_at' => now(),
    ]);

    $venue->forceDelete();

    // append-only audit: the historical id is kept, never nulled
    expect($event->fresh()->venue_id)->toBe($venue->id);
});
