<?php

use App\Models\AuditEvent;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('emits a venue.created event when a Venue is saved', function () {
    Venue::factory()->create(['name' => 'Test Venue']);

    expect(AuditEvent::query()->where('event_type', 'venue.created')->count())->toBe(1);
});

it('emits a venue.updated event with before/after diff', function () {
    $venue = Venue::factory()->create(['name' => 'Original']);

    $venue->update(['name' => 'Renamed']);

    $event = AuditEvent::query()->where('event_type', 'venue.updated')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and($event->payload_json['before']['name'])->toBe('Original')
        ->and($event->payload_json['after']['name'])->toBe('Renamed');
});

it('emits a venue.deleted event on soft-delete', function () {
    $venue = Venue::factory()->create();

    $venue->delete();

    expect(AuditEvent::query()->where('event_type', 'venue.deleted')->count())->toBe(1);
});

it('emits a venue.restored event when un-trashed', function () {
    $venue = Venue::factory()->create();
    $venue->delete();
    $venue->restore();

    expect(AuditEvent::query()->where('event_type', 'venue.restored')->count())->toBe(1);
});

it('emits space.created when a Space is saved and links the venue_id', function () {
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    $event = AuditEvent::query()->where('event_type', 'space.created')->latest('id')->first();
    expect($event->subject_id)->toBe($space->id)
        ->and($event->venue_id)->toBe($venue->id);
});

it('scrubs password from a user.created audit payload', function () {
    User::factory()->create();

    $event = AuditEvent::query()->where('event_type', 'user.created')->latest('id')->first();
    expect($event->payload_json)->not->toHaveKey('password')
        ->and($event->payload_json)->not->toHaveKey('remember_token')
        ->and($event->payload_json)->not->toHaveKey('two_factor_secret');
});

it('does not emit an updated event when no attributes changed', function () {
    $venue = Venue::factory()->create();
    $baseline = AuditEvent::query()->where('event_type', 'venue.updated')->count();

    $venue->save();

    expect(AuditEvent::query()->where('event_type', 'venue.updated')->count())->toBe($baseline);
});
