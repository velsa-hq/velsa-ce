<?php

use App\Models\AuditEvent;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records the minimum-viable event with just an event_type', function () {
    app(AuditLogger::class)->record('session.login');

    $event = AuditEvent::query()->where('event_type', 'session.login')->firstOrFail();
    expect($event->user_id)->toBeNull()
        ->and($event->venue_id)->toBeNull();
});

it('captures the authenticated user automatically', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    app(AuditLogger::class)->record('session.login');

    $event = AuditEvent::query()->where('event_type', 'session.login')->firstOrFail();
    expect($event->user_id)->toBe($user->id);
});

it('attaches a subject and infers venue_id from a Venue subject', function () {
    $venue = Venue::factory()->create();

    app(AuditLogger::class)->record('venue.viewed', subject: $venue, payload: ['change' => 'name']);

    $event = AuditEvent::query()->where('event_type', 'venue.viewed')->firstOrFail();
    expect($event->subject_type)->toBe(Venue::class)
        ->and($event->subject_id)->toBe($venue->id)
        ->and($event->venue_id)->toBe($venue->id)
        ->and($event->payload_json)->toBe(['change' => 'name']);
});

it('infers venue_id from a Space subject', function () {
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    app(AuditLogger::class)->record('space.viewed', subject: $space);

    $event = AuditEvent::query()->where('event_type', 'space.viewed')->firstOrFail();
    expect($event->venue_id)->toBe($venue->id);
});

it('allows explicit user + venue overrides', function () {
    $user = User::factory()->create();
    $venue = Venue::factory()->create();

    app(AuditLogger::class)->record('admin.action', user: $user, venue: $venue);

    $event = AuditEvent::query()->where('event_type', 'admin.action')->firstOrFail();
    expect($event->user_id)->toBe($user->id)
        ->and($event->venue_id)->toBe($venue->id);
});

it('stores payload as null when given an empty array', function () {
    app(AuditLogger::class)->record('session.login', payload: []);

    $event = AuditEvent::query()->where('event_type', 'session.login')->firstOrFail();
    expect($event->payload_json)->toBeNull();
});

it('captures the source IP on the recorded event', function () {
    $this->actingAs(User::factory()->create());

    app(AuditLogger::class)->record('session.login');

    $event = AuditEvent::query()->where('event_type', 'session.login')->firstOrFail();
    expect($event->ip)->not->toBeNull();
});
