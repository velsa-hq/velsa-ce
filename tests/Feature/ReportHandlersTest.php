<?php

use App\Enums\BookingStatus;
use App\Enums\ExhibitorOrderStatus;
use App\Enums\LeadStage;
use App\Enums\WorkOrderStatus;
use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\EventOutline;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\Lead;
use App\Models\OutlineItem;
use App\Models\ResourceInventory;
use App\Models\Space;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Reports\Handlers\ArAgingReport;
use App\Reports\Handlers\BookedLocationsReport;
use App\Reports\Handlers\EventStatusChangeReport;
use App\Reports\Handlers\FoodAndBeverageRequirementsReport;
use App\Reports\Handlers\InventoryUtilizationReport;
use App\Reports\Handlers\LocationAvailabilityReport;
use App\Reports\Handlers\SalesPipelineReport;
use App\Reports\Handlers\WorkOrderStatusReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('BookedLocations report returns rows for bookings in the date window', function () {
    $venue = Venue::factory()->create();
    Booking::factory()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(5)->addHours(8),
        'status' => BookingStatus::Definite->value,
        'total_cents' => 200_00,
    ]);

    $result = app(BookedLocationsReport::class)->run([]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->summary)->not->toBeEmpty();
});

it('BookedLocations report respects the venue_id filter', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    Booking::factory()->count(2)->create(['venue_id' => $venueA->id, 'start_at' => now()->addDay()]);
    Booking::factory()->create(['venue_id' => $venueB->id, 'start_at' => now()->addDay()]);

    $result = app(BookedLocationsReport::class)->run(['venue_id' => $venueA->id]);

    expect($result->rows)->toHaveCount(2);
});

it('SalesPipeline report computes weighted forecast across open stages', function () {
    Lead::factory()->atStage(LeadStage::Qualified)->create(['estimated_value_cents' => 100_000_00]);
    Lead::factory()->atStage(LeadStage::Won)->create(['estimated_value_cents' => 50_000_00]);

    $result = app(SalesPipelineReport::class)->run([]);

    expect($result->rows)->toHaveCount(2);
    // open leads only: 1 qualified at 25% = $25k weighted
    $weighted = collect($result->summary)->firstWhere('label', 'Weighted forecast');
    expect($weighted['value'])->toBe('$25,000');
});

it('SalesPipeline report filters by venue and expected-close date window', function () {
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();
    Lead::factory()->atStage(LeadStage::Qualified)->create(['venue_id' => $venueA->id, 'expected_close_date' => now()->addDays(10)]);
    Lead::factory()->atStage(LeadStage::Qualified)->create(['venue_id' => $venueB->id, 'expected_close_date' => now()->addDays(10)]);
    Lead::factory()->atStage(LeadStage::Qualified)->create(['venue_id' => $venueA->id, 'expected_close_date' => now()->addDays(90)]);

    // no params -> whole pipeline
    expect(app(SalesPipelineReport::class)->run([])->rows)->toHaveCount(3);

    // venue filter -> venueA's two leads
    expect(app(SalesPipelineReport::class)->run(['venue_id' => $venueA->id])->rows)->toHaveCount(2);

    // date window -> leads closing within 30 days (2 of 3)
    expect(app(SalesPipelineReport::class)->run(['to' => now()->addDays(30)->toDateString()])->rows)->toHaveCount(2);

    // combined -> venueA closing within 30 days = 1
    expect(app(SalesPipelineReport::class)->run([
        'venue_id' => $venueA->id,
        'to' => now()->addDays(30)->toDateString(),
    ])->rows)->toHaveCount(1);
});

it('ArAging report buckets balances by days outstanding', function () {
    $venue = Venue::factory()->create();
    $event = ExhibitorEvent::factory()->create(['booking_id' => Booking::factory()->create(['venue_id' => $venue->id])->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'total_cents' => 50_00,
        'paid_cents' => 0,
        'placed_at' => now(),
    ]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'placed_at' => now()->subDays(5),
    ]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Pending->value,
        'total_cents' => 200_00,
        'paid_cents' => 0,
        'placed_at' => now()->subDays(45),
    ]);

    $result = app(ArAgingReport::class)->run([]);

    expect($result->rows)->toHaveCount(3);
    $totals = collect($result->summary)->keyBy('label');
    // same-day = current, 5d = 1-30, 45d = 31-60
    expect($totals['Total outstanding']['value'])->toBe('$350.00')
        ->and($totals['Current']['value'])->toBe('$50.00')
        ->and($totals['1-30 days']['value'])->toBe('$100.00')
        ->and($totals['31-60 days']['value'])->toBe('$200.00')
        ->and($totals['61-90 days']['value'])->toBe('$0.00')
        ->and($totals['91+ days']['value'])->toBe('$0.00');
});

it('ArAging report excludes paid and cancelled orders', function () {
    $venue = Venue::factory()->create();
    $event = ExhibitorEvent::factory()->create(['booking_id' => Booking::factory()->create(['venue_id' => $venue->id])->id]);
    $exhibitor = Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Paid->value,
        'total_cents' => 100_00,
        'paid_cents' => 100_00,
    ]);
    ExhibitorOrder::factory()->create([
        'exhibitor_id' => $exhibitor->id,
        'status' => ExhibitorOrderStatus::Cancelled->value,
        'total_cents' => 100_00,
        'paid_cents' => 0,
    ]);

    $result = app(ArAgingReport::class)->run([]);

    expect($result->rows)->toBeEmpty();
});

it('WorkOrderStatus report counts overdue work orders', function () {
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Open->value,
        'scheduled_for' => now()->subDay(),
    ]);
    WorkOrder::factory()->create([
        'status' => WorkOrderStatus::Open->value,
        'scheduled_for' => now()->addDay(),
    ]);

    $result = app(WorkOrderStatusReport::class)->run([]);

    $overdue = collect($result->summary)->firstWhere('label', 'Overdue');
    expect($overdue['value'])->toBe('1');
});

it('InventoryUtilization report flags resources with >=80% deployed', function () {
    $venue = Venue::factory()->create();
    ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'name' => 'Chairs',
        'quantity_total' => 100,
        'quantity_available' => 10, // 90% deployed
    ]);
    ResourceInventory::factory()->create([
        'venue_id' => $venue->id,
        'name' => 'Tables',
        'quantity_total' => 100,
        'quantity_available' => 70, // 30% deployed
    ]);

    $result = app(InventoryUtilizationReport::class)->run([]);

    $needsReplenish = collect($result->summary)->firstWhere('label', 'Replenish (≥80%)');
    expect($needsReplenish['value'])->toBe('1')
        ->and($needsReplenish['hint'])->toBe('low stock');

    $chairs = collect($result->rows)->firstWhere('name', 'Chairs');
    expect($chairs['flag'])->toBe('replenish')
        ->and($chairs['utilization_pct'])->toBe(90);
});

it('LocationAvailability report excludes spaces with overlapping blocking bookings', function () {
    $venue = Venue::factory()->create();
    $busy = Space::factory()->for($venue)->create(['name' => 'Busy Hall']);
    $free = Space::factory()->for($venue)->create(['name' => 'Free Hall']);

    $booking = Booking::factory()->definite()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $busy->id,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);

    $result = app(LocationAvailabilityReport::class)->run([
        'from' => now()->addDay()->toDateString(),
        'to' => now()->addDays(3)->toDateString(),
    ]);

    $names = collect($result->rows)->pluck('name')->all();
    expect($names)->toContain('Free Hall')
        ->and($names)->not->toContain('Busy Hall');
});

it('LocationAvailability report ignores tentative bookings', function () {
    $venue = Venue::factory()->create();
    $space = Space::factory()->for($venue)->create(['name' => 'Tentative Hall']);

    $booking = Booking::factory()->tentative()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);
    BookingSpace::factory()->create([
        'booking_id' => $booking->id,
        'space_id' => $space->id,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(8),
    ]);

    $result = app(LocationAvailabilityReport::class)->run([
        'from' => now()->addDay()->toDateString(),
        'to' => now()->addDays(3)->toDateString(),
    ]);

    expect(collect($result->rows)->pluck('name')->all())->toContain('Tentative Hall');
});

it('FoodAndBeverage report includes only catering items in the window', function () {
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id, 'attendance_estimate' => 250]);
    $outline = EventOutline::factory()->create(['booking_id' => $booking->id]);

    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'catering',
        'title' => 'Lunch buffet',
        'scheduled_at' => now()->addDays(3),
        'duration_minutes' => 60,
    ]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'setup',
        'title' => 'Stage setup',
        'scheduled_at' => now()->addDays(3),
        'duration_minutes' => 90,
    ]);
    // out-of-window catering item, excluded
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'catering',
        'title' => 'Far-future banquet',
        'scheduled_at' => now()->addDays(60),
        'duration_minutes' => 120,
    ]);

    $result = app(FoodAndBeverageRequirementsReport::class)->run([
        'from' => now()->toDateString(),
        'to' => now()->addDays(7)->toDateString(),
    ]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['title'])->toBe('Lunch buffet')
        ->and($result->rows[0]['attendance'])->toBe(250);

    $covers = collect($result->summary)->firstWhere('label', 'Cumulative covers');
    expect($covers['value'])->toBe('250');
});

it('EventStatusChange report surfaces booking status transitions', function () {
    $booking = Booking::factory()->create(['status' => BookingStatus::Inquiry->value]);

    // Auditable trait writes a booking.updated row to audit_events
    $booking->update(['status' => BookingStatus::Definite->value]);

    $result = app(EventStatusChangeReport::class)->run(['hours' => 24]);

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows[0]['from'])->toBe(BookingStatus::Inquiry->value)
        ->and($result->rows[0]['to'])->toBe(BookingStatus::Definite->value)
        ->and($result->rows[0]['reference'])->toBe($booking->reference);
});

it('EventStatusChange report ignores booking updates that do not change status', function () {
    $booking = Booking::factory()->create(['status' => BookingStatus::Inquiry->value]);

    $booking->update(['name' => 'Renamed Event']);

    $result = app(EventStatusChangeReport::class)->run(['hours' => 24]);

    expect($result->rows)->toBeEmpty();
});

it('every handler returns a valid ReportResult shape', function () {
    foreach ([
        BookedLocationsReport::class,
        LocationAvailabilityReport::class,
        SalesPipelineReport::class,
        ArAgingReport::class,
        WorkOrderStatusReport::class,
        InventoryUtilizationReport::class,
        FoodAndBeverageRequirementsReport::class,
        EventStatusChangeReport::class,
    ] as $class) {
        $result = app($class)->run([]);
        expect($result->columns)->not->toBeEmpty()
            ->and($result->generatedAt)->not->toBeNull();
    }
});
