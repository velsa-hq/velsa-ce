<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ReportDefinition;
use App\Models\ReportRun;
use App\Models\Venue;
use App\Reports\AdHocReportRunner;
use App\Reports\DatasourceRegistry;
use App\Reports\ReportDatasource;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);

    $venueA = Venue::factory()->create(['name' => 'Convention Center']);
    $venueB = Venue::factory()->create(['name' => 'Equestrian Center']);
    $client = Client::factory()->create();

    Booking::factory()->create([
        'venue_id' => $venueA->id,
        'client_id' => $client->id,
        'status' => BookingStatus::Definite->value,
        'total_cents' => 50_000_00,
        'attendance_estimate' => 500,
    ]);
    Booking::factory()->create([
        'venue_id' => $venueA->id,
        'client_id' => $client->id,
        'status' => BookingStatus::Definite->value,
        'total_cents' => 30_000_00,
        'attendance_estimate' => 300,
    ]);
    Booking::factory()->create([
        'venue_id' => $venueB->id,
        'client_id' => $client->id,
        'status' => BookingStatus::Inquiry->value,
        'total_cents' => 5_000_00,
        'attendance_estimate' => 50,
    ]);

    $this->runner = app(AdHocReportRunner::class);
});

it('exposes the datasource catalog via DatasourceRegistry', function () {
    $catalog = app(DatasourceRegistry::class)->catalog();

    expect($catalog)->toHaveCount(6);

    $bookings = collect($catalog)->firstWhere('value', 'bookings');
    expect($bookings['fields'])->not->toBeEmpty()
        ->and($bookings['aggregations'])->not->toBeEmpty();
});

it('runs a flat report when no metrics/dimensions are set', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'filters_json' => [],
        'dimensions_json' => [],
        'metrics_json' => [],
        'row_limit' => 1000,
    ]);

    $result = $this->runner->run($def);

    expect($result->rows)->toHaveCount(3)
        ->and($result->columns)->not->toBeEmpty();
});

it('applies a numeric greater-than filter on money fields', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'filters_json' => [
            ['field' => 'total_cents', 'operator' => '>', 'value' => 10000],
        ],
    ]);

    $result = $this->runner->run($def);

    // 50k and 30k pass; 5k filtered out
    expect($result->rows)->toHaveCount(2);
});

it('applies an enum filter on status', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'filters_json' => [
            ['field' => 'status', 'operator' => '=', 'value' => 'definite'],
        ],
    ]);

    $result = $this->runner->run($def);

    expect($result->rows)->toHaveCount(2);
});

it('aggregates bookings grouped by venue with sum of total_cents', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'dimensions_json' => ['venue_name'],
        'metrics_json' => [
            ['field' => 'total_cents', 'aggregation' => 'sum', 'label' => 'Revenue'],
        ],
    ]);

    $result = $this->runner->run($def);

    expect($result->rows)->toHaveCount(2);
    $convention = collect($result->rows)
        ->firstWhere('venue_name', 'Convention Center');
    expect((int) $convention['m0_sum'])->toBe(80_000_00);
});

it('aggregates with a count metric over rows', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'dimensions_json' => ['status'],
        'metrics_json' => [
            ['field' => '', 'aggregation' => 'count', 'label' => 'Booking count'],
        ],
    ]);

    $result = $this->runner->run($def);

    $definite = collect($result->rows)->firstWhere('status', 'definite');
    $inquiry = collect($result->rows)->firstWhere('status', 'inquiry');
    expect((int) $definite['m0_count'])->toBe(2)
        ->and((int) $inquiry['m0_count'])->toBe(1);
});

it('rejects an unknown aggregation', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'metrics_json' => [
            ['field' => 'total_cents', 'aggregation' => 'unsafe_function'],
        ],
    ]);

    $this->runner->run($def);
})->throws(InvalidArgumentException::class);

it('silently drops filters that reference unknown fields', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'filters_json' => [
            ['field' => 'made_up_field', 'operator' => '=', 'value' => 'x'],
        ],
    ]);

    $result = $this->runner->run($def);

    // filter dropped -> all 3 returned
    expect($result->rows)->toHaveCount(3);
});

it('records a ReportRun row after each execution', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
    ]);

    $this->runner->run($def);

    $run = ReportRun::query()->where('report_definition_id', $def->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->row_count)->toBe(3)
        ->and($run->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('respects the row_limit cap', function () {
    $def = ReportDefinition::factory()->create([
        'datasource' => ReportDatasource::Bookings->value,
        'row_limit' => 2,
    ]);

    $result = $this->runner->run($def);

    expect($result->rows)->toHaveCount(2);
});
