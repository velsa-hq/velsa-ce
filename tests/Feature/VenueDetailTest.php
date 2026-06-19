<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Venue;
use App\Models\WorkOrderTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the venue show page with spaces, upcoming, and stats', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create(['name' => 'Test Venue', 'slug' => 'test-venue']);
    Space::factory()->for($venue)->create(['name' => 'Hall A', 'capacity' => 200]);
    Space::factory()->for($venue)->create(['name' => 'Hall B', 'capacity' => 50]);

    // completed counts toward revenue but not upcoming
    Booking::factory()->definite()->create([
        'venue_id' => $venue->id,
        'start_at' => now()->addDays(10),
        'end_at' => now()->addDays(10)->addHours(4),
        'total_cents' => 100_000,
    ]);
    Booking::factory()->create([
        'venue_id' => $venue->id,
        'status' => BookingStatus::Completed->value,
        'start_at' => now()->subDays(30),
        'end_at' => now()->subDays(30)->addHours(4),
        'total_cents' => 250_000,
    ]);

    WorkOrderTemplate::factory()->for($venue)->create([
        'name' => 'Weekly HVAC check',
        'recurrence_rrule' => 'FREQ=WEEKLY;BYDAY=MO',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/venues/test-venue')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('venues/show')
            ->where('venue.slug', 'test-venue')
            ->where('venue.name', 'Test Venue')
            ->has('venue.spaces', 2)
            ->has('upcoming_bookings', 1)
            ->has('work_order_templates', 1)
            ->where('stats.lifetime_bookings', 2)
            ->where('stats.confirmed_revenue_cents', 350_000)
            ->where('stats.space_count', 2)
            ->where('stats.total_capacity', 250)
        );
});

it('renders the edit page with current values', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create([
        'name' => 'Old Name',
        'slug' => 'old-name',
        'timezone' => 'America/Chicago',
        'address_json' => ['city' => 'Riverside', 'state' => 'FL'],
        'settings_json' => ['summary' => 'A description'],
    ]);

    $this->actingAs($user)
        ->get("/venues/{$venue->slug}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('venues/edit')
            ->where('venue.name', 'Old Name')
            ->where('venue.city', 'Riverside')
            ->where('venue.state', 'FL')
            ->where('venue.summary', 'A description')
        );
});

it('updates name + city + state + summary + active flag', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create([
        'name' => 'Old',
        'slug' => 'updateable',
        'address_json' => ['city' => 'Old City', 'state' => 'FL'],
        'settings_json' => [],
        'active_at' => null,
    ]);

    $this->actingAs($user)
        ->put("/venues/{$venue->slug}", [
            'name' => 'New Name',
            'city' => 'Crestview',
            'state' => 'fl',
            'timezone' => 'America/Chicago',
            'summary' => 'Updated summary',
            'is_active' => true,
        ])
        ->assertRedirect("/venues/{$venue->slug}");

    $venue->refresh();
    expect($venue->name)->toBe('New Name')
        ->and($venue->address_json['city'])->toBe('Crestview')
        ->and($venue->address_json['state'])->toBe('FL')
        ->and($venue->settings_json['summary'])->toBe('Updated summary')
        ->and($venue->active_at)->not->toBeNull();
});

it('clears active_at when is_active is unchecked', function () {
    $user = grantSuperAdmin();
    $venue = Venue::factory()->create([
        'slug' => 'deactivating',
        'active_at' => now()->subDays(30),
        'address_json' => [],
        'settings_json' => [],
    ]);

    $this->actingAs($user)
        ->put("/venues/{$venue->slug}", [
            'name' => $venue->name,
            'timezone' => $venue->timezone,
            'is_active' => false,
        ])
        ->assertRedirect("/venues/{$venue->slug}");

    expect($venue->fresh()->active_at)->toBeNull();
});

it('returns 404 for an unknown slug', function () {
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/venues/not-a-real-slug')
        ->assertNotFound();
});
