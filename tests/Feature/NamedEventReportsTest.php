<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\Space;
use App\Models\Venue;
use App\Reports\Handlers\CalendarOfEventsReport;
use App\Reports\Handlers\EventAttendanceReport;
use App\Reports\Handlers\EventBulletinReport;
use App\Reports\Handlers\EventScheduleReport;
use App\Reports\Handlers\EventServicesScheduleReport;
use App\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('all five new named reports are registered', function () {
    $registry = app(ReportRegistry::class);
    foreach ([
        'event-bulletin',
        'event-schedule',
        'calendar-of-events',
        'event-attendance',
        'event-services-schedule',
    ] as $slug) {
        expect($registry->has($slug))->toBeTrue();
    }
});

it('event-bulletin lists only events starting on the chosen date', function () {
    Booking::factory()->create([
        'name' => 'Today event',
        'start_at' => now()->setTime(10, 0),
        'end_at' => now()->setTime(15, 0),
    ]);
    Booking::factory()->create([
        'name' => 'Tomorrow event',
        'start_at' => now()->addDay()->setTime(10, 0),
        'end_at' => now()->addDay()->setTime(15, 0),
    ]);

    $result = app(EventBulletinReport::class)->run(['date' => now()->toDateString()]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['name'])->toBe('Today event');
});

it('event-schedule respects the venue_id filter', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    Booking::factory()->create(['venue_id' => $venueA->id, 'start_at' => now()->addDays(2)]);
    Booking::factory()->create(['venue_id' => $venueA->id, 'start_at' => now()->addDays(3)]);
    Booking::factory()->create(['venue_id' => $venueB->id, 'start_at' => now()->addDays(2)]);

    $result = app(EventScheduleReport::class)->run(['venue_id' => $venueA->id]);

    expect($result->rows)->toHaveCount(2);
});

it('event-schedule lists the spaces/locations for each event', function () {
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id, 'name' => 'Grand Ballroom']);
    $booking = Booking::factory()->create(['venue_id' => $venue->id, 'start_at' => now()->addDays(2)]);
    BookingSpace::factory()->create(['booking_id' => $booking->id, 'space_id' => $space->id]);

    $result = app(EventScheduleReport::class)->run([]);

    expect($result->rows[0]['spaces'])->toContain('Grand Ballroom');
});

it('calendar-of-events shows only definite + completed events', function () {
    Booking::factory()->withStatus(BookingStatus::Definite)
        ->create(['start_at' => now()->addDays(3), 'end_at' => now()->addDays(3)->addHours(4)]);
    Booking::factory()->withStatus(BookingStatus::Completed)
        ->create(['start_at' => now()->addDays(5), 'end_at' => now()->addDays(5)->addHours(4)]);
    Booking::factory()->withStatus(BookingStatus::Tentative)
        ->create(['start_at' => now()->addDays(7), 'end_at' => now()->addDays(7)->addHours(4)]);
    Booking::factory()->withStatus(BookingStatus::Inquiry)
        ->create(['start_at' => now()->addDays(9), 'end_at' => now()->addDays(9)->addHours(4)]);

    $result = app(CalendarOfEventsReport::class)->run([]);

    expect($result->rows)->toHaveCount(2);
});

it('event-attendance computes estimate vs actual variance', function () {
    Booking::factory()->withStatus(BookingStatus::Completed)->create([
        'start_at' => now()->subDays(10),
        'end_at' => now()->subDays(10)->addHours(4),
        'attendance_estimate' => 100,
        'attendance_actual' => 120,
    ]);

    $result = app(EventAttendanceReport::class)->run([]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['estimate'])->toBe('100')
        ->and($result->rows[0]['actual'])->toBe('120')
        ->and($result->rows[0]['variance'])->toBe('+20')
        ->and($result->rows[0]['variance_pct'])->toBe('+20%');
});

it('event-services-schedule lists outline items in the window', function () {
    $booking = Booking::factory()->create([
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);
    $outline = EventOutline::factory()->create(['booking_id' => $booking->id]);

    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(2)->setTime(8, 0),
        'duration_minutes' => 60,
        'department' => 'setup',
        'title' => 'Room flip',
    ]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        'duration_minutes' => 30,
        'department' => 'av',
        'title' => 'Sound check',
    ]);
    // outside the default 14-day window
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(30)->setTime(8, 0),
        'duration_minutes' => 60,
        'department' => 'setup',
    ]);

    $result = app(EventServicesScheduleReport::class)->run([]);

    expect($result->rows)->toHaveCount(2)
        ->and(collect($result->rows)->pluck('title')->all())
        ->toContain('Room flip', 'Sound check');
});

it('event-services-schedule respects the department filter', function () {
    $booking = Booking::factory()->create([
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);
    $outline = EventOutline::factory()->create(['booking_id' => $booking->id]);

    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(2)->setTime(8, 0),
        'duration_minutes' => 60,
        'department' => 'setup',
    ]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        'duration_minutes' => 30,
        'department' => 'av',
    ]);

    $result = app(EventServicesScheduleReport::class)->run([
        'department' => 'setup',
    ]);

    expect($result->rows)->toHaveCount(1);
});
