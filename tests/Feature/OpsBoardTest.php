<?php

use App\Models\Booking;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

/** user with bookings.view via ops_lead */
function opsBoardUser(): User
{
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRoleAt(Venue::factory()->create(), 'ops_lead');

    return $user;
}

it('renders the ops board with items grouped by date and department', function () {
    $user = opsBoardUser();
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    $outline->publish();
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDays(2)->setTime(10, 0),
        'department' => 'catering',
        'title' => 'Lunch prep',
    ]);

    $response = $this->actingAs($user)->get('/ops/board');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ops/board')
            ->where('total_items', 1)
            ->where('filters.days', 14)
        );
});

it('honors the days parameter (clamped to 7-28)', function () {
    $user = opsBoardUser();

    $this->actingAs($user)->get('/ops/board?days=21')->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.days', 21));

    // out-of-range clamps to max 28
    $this->actingAs($user)->get('/ops/board?days=999')->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.days', 28));
});

it('filters by department when ?department= is provided', function () {
    $user = opsBoardUser();
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    $outline->publish();
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDay(),
        'department' => 'setup',
    ]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'scheduled_at' => now()->addDay(),
        'department' => 'catering',
    ]);

    $this->actingAs($user)->get('/ops/board?department=setup')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total_items', 1));
});

it('filters by venue_id when provided', function () {
    $user = opsBoardUser();
    $venueA = Venue::factory()->create();
    $venueB = Venue::factory()->create();

    $bookingA = Booking::factory()->create(['venue_id' => $venueA->id]);
    $outlineA = EventOutline::query()->create(['booking_id' => $bookingA->id]);
    $outlineA->publish();
    OutlineItem::factory()->create([
        'event_outline_id' => $outlineA->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $bookingB = Booking::factory()->create(['venue_id' => $venueB->id]);
    $outlineB = EventOutline::query()->create(['booking_id' => $bookingB->id]);
    $outlineB->publish();
    OutlineItem::factory()->count(3)->create([
        'event_outline_id' => $outlineB->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->get("/ops/board?venue_id={$venueB->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total_items', 3));
});

it('omits items from unpublished outlines', function () {
    $user = opsBoardUser();
    $venue = Venue::factory()->create();
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);

    // unpublished - must not show
    $draft = EventOutline::query()->create(['booking_id' => $booking->id]);
    expect($draft->isPublished())->toBeFalse();
    OutlineItem::factory()->count(2)->create([
        'event_outline_id' => $draft->id,
        'scheduled_at' => now()->addDay(),
    ]);

    // published - should show
    $booking2 = Booking::factory()->create(['venue_id' => $venue->id]);
    $published = EventOutline::query()->create(['booking_id' => $booking2->id]);
    $published->publish();
    OutlineItem::factory()->create([
        'event_outline_id' => $published->id,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($user)->get('/ops/board')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total_items', 1));
});
