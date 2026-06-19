<?php

use App\Models\Space;
use App\Models\SpaceKind;
use App\Models\Venue;
use App\Services\SystemSettings\SystemSettings;
use App\Support\AreaUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function useMetric(): void
{
    app(SystemSettings::class)->set('defaults.use_metric_units', true);
}

it('defaults to imperial (square feet) with identity conversion', function () {
    expect(AreaUnit::isMetric())->toBeFalse()
        ->and(AreaUnit::label())->toBe('ft²')
        ->and(AreaUnit::fromSqft(5000))->toBe(5000)
        ->and(AreaUnit::toSqft(5000))->toBe(5000)
        ->and(AreaUnit::format(5000))->toBe('5,000 ft²')
        ->and(AreaUnit::format(null))->toBe('-');
});

it('converts to/from square metres when metric is enabled', function () {
    useMetric();

    expect(AreaUnit::isMetric())->toBeTrue()
        ->and(AreaUnit::label())->toBe('m²')
        // 5000 sqft ~= 464.5 m2; 50 m2 ~= 538 sqft
        ->and(AreaUnit::fromSqft(5000))->toBe(465)
        ->and(AreaUnit::toSqft(50))->toBe(538)
        ->and(AreaUnit::format(5000))->toBe('465 m²');
});

it('stores space area canonically in sqft when entered as metres', function () {
    useMetric();
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create();
    SpaceKind::factory()->create(['key' => 'room', 'label' => 'Room', 'is_active' => true]);

    $this->actingAs($user)
        ->post("/venues/{$venue->slug}/spaces", [
            'name' => 'Metric Hall',
            'kind' => 'room',
            'sqft' => 50, // entered as m2
            'bookable_unit' => 'daily',
        ])
        ->assertRedirect();

    $space = Space::query()->where('name', 'Metric Hall')->first();
    // 50 m2 -> ~538 sqft canonical
    expect($space->sqft)->toBe(538);
});

it('exposes the measurement config in shared Inertia props', function () {
    useMetric();
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/venues')
        ->assertInertia(fn ($page) => $page
            ->where('measurement.metric', true)
            ->where('measurement.unit', 'm²'));
});
