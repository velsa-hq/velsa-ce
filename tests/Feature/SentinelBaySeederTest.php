<?php

use App\Enums\BookingStatus;
use App\Enums\LeadStage;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ExhibitorEvent;
use App\Models\Lead;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use App\Services\SystemSettings\SystemSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SentinelBayBookingsSeeder;
use Database\Seeders\SentinelBayBrandingSeeder;
use Database\Seeders\SentinelBayExhibitorsSeeder;
use Database\Seeders\SentinelBaySalesSeeder;
use Database\Seeders\SentinelBayUsersSeeder;
use Database\Seeders\SentinelBayVenuesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('SentinelBayVenuesSeeder seeds 5 active + 1 coming-soon venues', function () {
    $this->seed(SentinelBayVenuesSeeder::class);

    expect(Venue::query()->count())->toBe(6)
        ->and(Venue::query()->active()->pluck('slug')->all())->toEqualCanonicalizing([
            'pelican-cove-convention-center',
            'aquila-performing-arts-hall',
            'sentinel-bay-sports-recreation-complex',
            'driftwood-fairgrounds',
            'heron-creek-retreat',
        ])
        ->and(Venue::query()->comingSoon()->pluck('slug')->all())->toBe([
            'marlin-bay-welcome-center',
        ]);
});

it('builds the Coral Reef Grand Ballroom partition hierarchy', function () {
    $this->seed(SentinelBayVenuesSeeder::class);

    $venue = Venue::where('slug', 'pelican-cove-convention-center')->firstOrFail();
    $root = $venue->spaces()->where('name', 'Coral Reef Grand Ballroom + Exhibit Hall A')->firstOrFail();
    $sectionB = $venue->spaces()->where('name', 'Coral Reef Grand Ballroom B')->firstOrFail();
    $sectionC = $venue->spaces()->where('name', 'Coral Reef Grand Ballroom C')->firstOrFail();
    $sectionD = $venue->spaces()->where('name', 'Coral Reef Grand Ballroom D')->firstOrFail();

    expect($root->kind)->toBe('ballroom')
        ->and($root->parent_space_id)->toBeNull()
        ->and($sectionB->parent_space_id)->toBe($root->id)
        ->and($sectionC->parent_space_id)->toBe($sectionB->id)
        ->and($sectionD->parent_space_id)->toBe($sectionB->id);
});

it('gives the sports complex an Arena + outdoor field mix', function () {
    $this->seed(SentinelBayVenuesSeeder::class);

    $sports = Venue::where('slug', 'sentinel-bay-sports-recreation-complex')->firstOrFail();

    expect($sports->spaces()->where('kind', 'arena')->count())->toBeGreaterThanOrEqual(2)
        ->and($sports->spaces()->where('kind', 'outdoor_field')->count())->toBe(3);
});

it('SentinelBayUsersSeeder seeds a roster of 14', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);

    $staff = User::query()
        ->where('email', 'like', '%@sentinelbay.ca.gov')
        ->where('email', '!=', 'admin@sentinelbay.ca.gov')
        ->get();

    expect($staff->count())->toBe(14)
        ->and($staff->pluck('email')->all())->toContain(
            'jordan.pierce@sentinelbay.ca.gov',
            'rachel.tate@sentinelbay.ca.gov',
            'carlos.mendez@sentinelbay.ca.gov',
            'hannah.wallace@sentinelbay.ca.gov',
        );
});

it('SentinelBaySalesSeeder seeds clients across sectors', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);

    $names = Client::query()->pluck('name')->all();

    expect($names)->toContain(
        'Sandbar Inn & Spa',
        'Tidewater Bay Casino',
        'Aquila State University',
        'Gulfwind Technical Institute',
        'Sentinel Bay Naval Auxiliary',
        'Sentinel Bay County Schools District',
    );
});

it('SentinelBaySalesSeeder produces leads in every stage', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);

    $stages = Lead::query()
        ->pluck('stage')
        ->map(fn ($s) => $s instanceof LeadStage ? $s->value : $s)
        ->unique()
        ->values()
        ->all();

    foreach (LeadStage::cases() as $stage) {
        expect($stages)->toContain($stage->value);
    }
});

it('SentinelBayBookingsSeeder produces bookings across every status', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);
    $this->seed(SentinelBayBookingsSeeder::class);

    $statuses = Booking::query()
        ->pluck('status')
        ->map(fn ($s) => $s instanceof BookingStatus ? $s->value : $s)
        ->unique()
        ->values()
        ->all();

    foreach (BookingStatus::cases() as $status) {
        expect($statuses)->toContain($status->value);
    }
});

it('seeds at least one booking at every active venue', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);
    $this->seed(SentinelBayBookingsSeeder::class);

    foreach (Venue::query()->active()->get() as $venue) {
        expect(Booking::query()->where('venue_id', $venue->id)->count())
            ->toBeGreaterThan(0, "expected bookings at {$venue->name}");
    }
});

it('SentinelBayExhibitorsSeeder creates 2+ exhibitor events with orders', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);
    $this->seed(SentinelBayBookingsSeeder::class);
    $this->seed(SentinelBayExhibitorsSeeder::class);

    $events = ExhibitorEvent::query()->get();

    expect($events->count())->toBeGreaterThanOrEqual(2)
        ->and($events->first()->exhibitors()->count())->toBeGreaterThan(0);
});

it('SentinelBayBrandingSeeder rewrites branding keys to Sentinel Bay values', function () {
    $this->seed(SentinelBayBrandingSeeder::class);

    $settings = app(SystemSettings::class);

    expect($settings->get('branding.app_name'))->toBe('Sentinel Bay')
        ->and($settings->get('branding.app_title'))->toBe('Sentinel Bay County Tourism & Convention Bureau')
        ->and($settings->get('branding.logo_path'))->toBe('/branding/sentinel-bay/logo.svg')
        ->and($settings->get('branding.stock_background_folder'))->toBe('branding/sentinel-bay/stock');
});

it('SentinelBayUsersSeeder grants the demo admin super_admin at every venue', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);

    $admin = User::query()->where('email', 'admin@sentinelbay.ca.gov')->first();

    expect($admin)->not->toBeNull();

    foreach (Venue::query()->active()->get() as $venue) {
        expect($admin->fresh()->roleAt($venue))->toBe('super_admin');
    }
});

it('is idempotent: running every seeder twice does not duplicate', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);
    $this->seed(SentinelBayBookingsSeeder::class);
    $this->seed(SentinelBayExhibitorsSeeder::class);

    $before = [
        'venues' => Venue::query()->count(),
        'spaces' => Space::query()->count(),
        'staff' => User::query()->where('email', 'like', '%@sentinelbay.ca.gov')->count(),
        'clients' => Client::query()->count(),
        'bookings' => Booking::query()->count(),
        'events' => ExhibitorEvent::query()->count(),
    ];

    $this->seed(SentinelBayVenuesSeeder::class);
    $this->seed(SentinelBayUsersSeeder::class);
    $this->seed(SentinelBaySalesSeeder::class);
    $this->seed(SentinelBayBookingsSeeder::class);
    $this->seed(SentinelBayExhibitorsSeeder::class);

    expect(Venue::query()->count())->toBe($before['venues'])
        ->and(Space::query()->count())->toBe($before['spaces'])
        ->and(User::query()->where('email', 'like', '%@sentinelbay.ca.gov')->count())->toBe($before['staff'])
        ->and(Client::query()->count())->toBe($before['clients'])
        ->and(Booking::query()->count())->toBe($before['bookings'])
        ->and(ExhibitorEvent::query()->count())->toBe($before['events']);
});
