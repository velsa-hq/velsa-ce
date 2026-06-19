<?php

use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorHandbookAcknowledgement;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function exhibitorAtVenue(Venue $venue): Exhibitor
{
    $booking = Booking::factory()->create(['venue_id' => $venue->id]);
    $event = ExhibitorEvent::factory()->create(['booking_id' => $booking->id]);

    return Exhibitor::factory()->create(['exhibitor_event_id' => $event->id]);
}

it('publishes the exhibitor handbook from the venue edit form', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();

    $this->actingAs($admin)
        ->put("/venues/{$venue->slug}", [
            'name' => $venue->name,
            'timezone' => 'America/Chicago',
            'is_active' => true,
            'exhibitor_handbook_md' => "# Rules\n\n- No open flames",
            'exhibitor_handbook_published' => true,
        ])
        ->assertRedirect();

    $venue->refresh();
    expect($venue->exhibitor_handbook_md)->toContain('No open flames')
        ->and($venue->exhibitor_handbook_published_at)->not->toBeNull()
        ->and($venue->hasPublishedExhibitorHandbook())->toBeTrue()
        ->and($venue->exhibitorHandbookHtml())->toContain('<li>No open flames</li>');
});

it('does not publish an empty handbook even if the box is ticked', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();

    $this->actingAs($admin)->put("/venues/{$venue->slug}", [
        'name' => $venue->name, 'timezone' => 'America/Chicago', 'is_active' => true,
        'exhibitor_handbook_md' => '', 'exhibitor_handbook_published' => true,
    ])->assertRedirect();

    expect($venue->fresh()->hasPublishedExhibitorHandbook())->toBeFalse();
});

it('shows the published handbook to an exhibitor and records acknowledgement', function () {
    $venue = Venue::factory()->create([
        'exhibitor_handbook_md' => '## Welcome',
        'exhibitor_handbook_published_at' => now(),
    ]);
    $exhibitor = exhibitorAtVenue($venue);

    $this->actingAs($exhibitor, 'exhibitor')
        ->get('/portal/handbook')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('portal/handbook')->where('acknowledged_at', null));

    $this->actingAs($exhibitor, 'exhibitor')->post('/portal/handbook/acknowledge')->assertRedirect();

    $this->assertDatabaseHas('exhibitor_handbook_acknowledgements', [
        'exhibitor_id' => $exhibitor->id, 'venue_id' => $venue->id,
    ]);
});

it('keeps the first acknowledgement date (idempotent)', function () {
    $venue = Venue::factory()->create(['exhibitor_handbook_md' => 'x', 'exhibitor_handbook_published_at' => now()]);
    $exhibitor = exhibitorAtVenue($venue);

    $this->actingAs($exhibitor, 'exhibitor')->post('/portal/handbook/acknowledge');
    $first = ExhibitorHandbookAcknowledgement::sole()->acknowledged_at;
    $this->actingAs($exhibitor, 'exhibitor')->post('/portal/handbook/acknowledge');

    expect(ExhibitorHandbookAcknowledgement::count())->toBe(1)
        ->and(ExhibitorHandbookAcknowledgement::sole()->acknowledged_at->equalTo($first))->toBeTrue();
});

it('redirects an exhibitor to the dashboard when no handbook is published', function () {
    $venue = Venue::factory()->create();
    $exhibitor = exhibitorAtVenue($venue);

    $this->actingAs($exhibitor, 'exhibitor')->get('/portal/handbook')->assertRedirect('/portal');
});
