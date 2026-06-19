<?php

use App\Models\Booking;
use App\Models\Department;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an outline lazily on first visit to /bookings/{id}/outline', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();

    expect(EventOutline::query()->where('booking_id', $booking->id)->exists())->toBeFalse();

    $this->actingAs($user)->get("/bookings/{$booking->id}/outline")->assertOk();

    expect(EventOutline::query()->where('booking_id', $booking->id)->exists())->toBeTrue();
});

it('appends an item via POST /bookings/{id}/outline/items', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    Department::factory()->system()->create(['key' => 'setup', 'label' => 'Setup']);

    $this->actingAs($user)->post("/bookings/{$booking->id}/outline/items", [
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'duration_minutes' => 60,
        'department' => 'setup',
        'title' => 'Stage assembly',
    ])->assertRedirect();

    $outline = EventOutline::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($outline->items()->count())->toBe(1)
        ->and($outline->items()->first()->title)->toBe('Stage assembly');
});

it('rejects an item whose department is not an active taxonomy key', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    Department::factory()->inactive()->create(['key' => 'retired_dept', 'label' => 'Retired']);

    // unknown and inactive keys both fail the active-exists rule
    $this->actingAs($user)->post("/bookings/{$booking->id}/outline/items", [
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'duration_minutes' => 30,
        'department' => 'not_a_dept',
        'title' => 'Valid title',
    ])->assertSessionHasErrors(['department']);

    $this->actingAs($user)->post("/bookings/{$booking->id}/outline/items", [
        'scheduled_at' => now()->addDay()->toDateTimeString(),
        'duration_minutes' => 30,
        'department' => 'retired_dept',
        'title' => 'Valid title',
    ])->assertSessionHasErrors(['department']);
});

it('removes an item via DELETE /outline-items/{id}', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    $item = OutlineItem::factory()->create(['event_outline_id' => $outline->id]);

    $this->actingAs($user)->delete("/outline-items/{$item->id}")->assertRedirect();

    expect(OutlineItem::query()->find($item->id))->toBeNull();
});

it('publish() increments the version and stamps published_at', function () {
    $user = grantSuperAdmin();
    $booking = Booking::factory()->create();

    $this->actingAs($user)->post("/bookings/{$booking->id}/outline/publish")->assertRedirect();

    $outline = EventOutline::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($outline->published_version)->toBe(1)
        ->and($outline->published_at)->not->toBeNull();

    $this->actingAs($user)->post("/bookings/{$booking->id}/outline/publish")->assertRedirect();

    expect($outline->fresh()->published_version)->toBe(2);
});

it('resolves the department label from the taxonomy', function () {
    Department::factory()->system()->create(['key' => 'catering', 'label' => 'Catering', 'color' => 'amber']);
    $outline = EventOutline::factory()->create();
    $item = OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'catering',
    ]);

    expect($item->department)->toBe('catering')
        ->and($item->departmentLabel())->toBe('Catering')
        ->and($item->departmentColor())->toBe('amber');
});

it('falls back to a title-cased label when the department has no taxonomy row', function () {
    $item = OutlineItem::factory()->create(['department' => 'crowd_control']);

    expect($item->departmentLabel())->toBe('Crowd Control')
        ->and($item->departmentColor())->toBe('slate');
});

it('endsAt() returns scheduled_at + duration_minutes', function () {
    $item = OutlineItem::factory()->create([
        'scheduled_at' => '2026-08-15 14:00:00',
        'duration_minutes' => 45,
    ]);

    expect($item->endsAt()->format('Y-m-d H:i:s'))->toBe('2026-08-15 14:45:00');
});

it('scopes ->between() to items inside the window', function () {
    $outline = EventOutline::factory()->create();
    OutlineItem::factory()->create(['event_outline_id' => $outline->id, 'scheduled_at' => now()->addDays(2)]);
    OutlineItem::factory()->create(['event_outline_id' => $outline->id, 'scheduled_at' => now()->addDays(20)]);

    expect(OutlineItem::query()->between(now(), now()->addDays(7))->count())->toBe(1);
});

it('scopes ->forDepartment() to a single department', function () {
    $outline = EventOutline::factory()->create();
    OutlineItem::factory()->create(['event_outline_id' => $outline->id, 'department' => 'setup']);
    OutlineItem::factory()->create(['event_outline_id' => $outline->id, 'department' => 'catering']);

    expect(OutlineItem::query()->forDepartment('setup')->count())->toBe(1);
});
