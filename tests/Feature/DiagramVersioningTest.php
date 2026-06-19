<?php

use App\Models\Booking;
use App\Models\Diagram;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->venue = Venue::factory()->create();
    $this->space = Space::factory()->create(['venue_id' => $this->venue->id]);
    $this->booking = Booking::factory()->create(['venue_id' => $this->venue->id]);
});

it('saves a first version at v1 and marks it current', function () {
    $diagram = Diagram::query()->create([
        'booking_id' => $this->booking->id,
        'space_id' => $this->space->id,
        'name' => 'Test',
        'scale_px_per_foot' => 10,
    ]);

    $version = $diagram->saveVersion([
        ['id' => 'o1', 'type' => 'round_table_60', 'x' => 100, 'y' => 100],
    ]);

    expect($version->version)->toBe(1)
        ->and($diagram->fresh()->current_version_id)->toBe($version->id)
        ->and($version->objects_json)->toBe([
            ['id' => 'o1', 'type' => 'round_table_60', 'x' => 100, 'y' => 100],
        ]);
});

it('increments version on each save and preserves prior versions', function () {
    $diagram = Diagram::query()->create([
        'booking_id' => $this->booking->id,
        'space_id' => $this->space->id,
        'name' => 'Test',
        'scale_px_per_foot' => 10,
    ]);

    $v1 = $diagram->saveVersion([['id' => 'o1', 'type' => 'chair', 'x' => 0, 'y' => 0]]);
    $v2 = $diagram->saveVersion([['id' => 'o1', 'type' => 'chair', 'x' => 50, 'y' => 50]]);
    $v3 = $diagram->saveVersion([
        ['id' => 'o1', 'type' => 'chair', 'x' => 50, 'y' => 50],
        ['id' => 'o2', 'type' => 'stage_4x8', 'x' => 200, 'y' => 100],
    ]);

    expect($v1->version)->toBe(1)
        ->and($v2->version)->toBe(2)
        ->and($v3->version)->toBe(3)
        ->and($diagram->versions()->count())->toBe(3)
        ->and($diagram->fresh()->current_version_id)->toBe($v3->id);

    expect($diagram->versions()->where('version', 1)->first()->objects_json)->toEqualCanonicalizing([
        ['id' => 'o1', 'type' => 'chair', 'x' => 0, 'y' => 0],
    ]);
});

it('persists a diagram via the POST endpoint and creates v1', function () {
    $user = grantSuperAdmin();
    $this->booking->spaces()->create([
        'space_id' => $this->space->id,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDay()->addHours(4),
    ]);

    $response = $this->actingAs($user)->post("/bookings/{$this->booking->id}/diagram", [
        'space_id' => $this->space->id,
        'objects' => [
            ['id' => 'a', 'type' => 'round_table_60', 'x' => 100, 'y' => 100],
            ['id' => 'b', 'type' => 'round_table_60', 'x' => 200, 'y' => 100],
        ],
    ]);

    $response->assertRedirect();
    $diagram = Diagram::query()->where('booking_id', $this->booking->id)->firstOrFail();
    expect($diagram->versions()->count())->toBe(1)
        ->and($diagram->currentVersion->objects_json)->toHaveCount(2);
});

it('rejects saving to a locked diagram', function () {
    $user = grantSuperAdmin();
    Diagram::query()->create([
        'booking_id' => $this->booking->id,
        'space_id' => $this->space->id,
        'name' => 'Test',
        'scale_px_per_foot' => 10,
        'locked_at' => now(),
    ]);

    $response = $this->actingAs($user)->post("/bookings/{$this->booking->id}/diagram", [
        'space_id' => $this->space->id,
        'objects' => [['id' => 'a', 'type' => 'round_table_60', 'x' => 0, 'y' => 0]],
    ]);

    $response->assertStatus(423);
});

it('rejects payloads with malformed objects', function () {
    $user = grantSuperAdmin();
    $response = $this->actingAs($user)->post("/bookings/{$this->booking->id}/diagram", [
        'space_id' => $this->space->id,
        'objects' => [
            ['type' => 'round_table_60'], // missing x, y
        ],
    ]);

    $response->assertSessionHasErrors(['objects.0.x', 'objects.0.y']);
});
