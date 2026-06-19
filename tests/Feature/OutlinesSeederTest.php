<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\Venue;
use Database\Seeders\OutlinesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a 13-item base outline for every in-window booking', function () {
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create([
        'venue_id' => $venue->id,
        'status' => BookingStatus::Definite->value,
        'start_at' => now()->addDays(3)->setTime(9, 0),
        'end_at' => now()->addDays(3)->setTime(17, 0),
        'kind' => 'training', // adds 4 kind-specific items
    ]);

    (new OutlinesSeeder)->run();

    $outline = EventOutline::query()
        ->where('booking_id', $booking->id)
        ->firstOrFail();
    $items = OutlineItem::query()
        ->where('event_outline_id', $outline->id)
        ->get();

    // 13 base + 4 training-specific
    expect($items->count())->toBe(17);

    $deptValues = $items->pluck('department')->unique();
    expect($deptValues->count())->toBeGreaterThanOrEqual(8);
});

it('does not seed outlines outside the ops window', function () {
    $venue = Venue::factory()->create();
    Booking::factory()->create([
        'venue_id' => $venue->id,
        'status' => BookingStatus::Definite->value,
        'start_at' => now()->subMonths(6),
        'end_at' => now()->subMonths(6)->addHours(8),
    ]);
    Booking::factory()->create([
        'venue_id' => $venue->id,
        'status' => BookingStatus::Definite->value,
        'start_at' => now()->addMonths(6),
        'end_at' => now()->addMonths(6)->addHours(8),
    ]);

    (new OutlinesSeeder)->run();

    expect(EventOutline::query()->count())->toBe(0);
});

it('publishes every seeded outline (published_at filled, version >= 1)', function () {
    $venue = Venue::factory()->create();
    Booking::factory()->count(2)->create([
        'venue_id' => $venue->id,
        'status' => BookingStatus::Definite->value,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(6),
    ]);

    (new OutlinesSeeder)->run();

    EventOutline::query()->get()->each(function (EventOutline $outline) {
        expect($outline->published_at)->not->toBeNull();
        expect($outline->published_version)->toBeGreaterThanOrEqual(1);
    });
});
