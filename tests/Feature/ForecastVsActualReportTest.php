<?php

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Venue;
use App\Reports\Handlers\ForecastVsActualReport;
use App\Reports\ReportRegistry;
use App\Services\Accounting\ValueFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is registered under the forecast-vs-actual slug', function () {
    expect(app(ReportRegistry::class)->has('forecast-vs-actual'))->toBeTrue();
});

it('reports forecast vs actual revenue and attendance for an event', function () {
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->subDays(5),
        'end_at' => now()->subDays(5)->addHours(6),
        'status' => BookingStatus::Completed->value,
        'total_cents' => 500_000,        // forecast revenue $5,000
        'attendance_estimate' => 100,
        'attendance_actual' => 120,
    ]);

    // actual invoiced revenue $4,500 (non-void)
    Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $booking->id,
        'status' => InvoiceStatus::Issued->value,
        'total_cents' => 450_000,
    ]);
    // void invoice must be excluded from actual
    Invoice::factory()->create([
        'invoiceable_type' => Booking::class,
        'invoiceable_id' => $booking->id,
        'status' => InvoiceStatus::Void->value,
        'total_cents' => 999_999,
    ]);

    $result = app(ForecastVsActualReport::class)->run([]);

    expect($result->rows)->toHaveCount(1);
    $row = $result->rows[0];
    expect($row['forecast_revenue'])->toBe(ValueFormatter::usd(500_000))
        ->and($row['actual_revenue'])->toBe(ValueFormatter::usd(450_000))
        ->and($row['est_attendance'])->toBe('100')
        ->and($row['actual_attendance'])->toBe('120')
        ->and($row['revenue_variance_pct'])->toBe('-10%')    // 450k vs 500k
        ->and($row['attendance_variance_pct'])->toBe('+20%'); // 120 vs 100

    $summary = collect($result->summary)->keyBy('label');
    expect($summary['Forecast revenue']['value'])->toBe(ValueFormatter::usd(500_000))
        ->and($summary['Actual (invoiced) revenue']['value'])->toBe(ValueFormatter::usd(450_000));
});

it('respects the venue filter and the date window', function () {
    $a = Venue::factory()->create();
    $b = Venue::factory()->create();
    Booking::factory()->create(['venue_id' => $a->id, 'start_at' => now()->subDay(), 'status' => BookingStatus::Definite->value]);
    Booking::factory()->create(['venue_id' => $b->id, 'start_at' => now()->subDay(), 'status' => BookingStatus::Definite->value]);
    Booking::factory()->create(['venue_id' => $a->id, 'start_at' => now()->subYears(2), 'status' => BookingStatus::Definite->value]);

    $result = app(ForecastVsActualReport::class)->run(['venue_id' => $a->id]);

    expect($result->rows)->toHaveCount(1); // venue A, in the default 90-day window
});
