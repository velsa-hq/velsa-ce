<?php

use App\Enums\BookingNarrativeKind;
use App\Models\Booking;
use App\Models\BookingNarrative;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->booking = Booking::factory()->create();
});

it('appends a narrative entry via the controller endpoint', function () {
    $response = $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'call',
            'body' => 'Client called to confirm AV needs.',
        ]);

    $response->assertSessionDoesntHaveErrors()->assertRedirect();

    $narrative = BookingNarrative::query()->latest('id')->firstOrFail();
    expect($narrative->booking_id)->toBe($this->booking->id)
        ->and($narrative->author_user_id)->toBe($this->user->id)
        ->and($narrative->kind)->toBe(BookingNarrativeKind::Call)
        ->and($narrative->body)->toBe('Client called to confirm AV needs.');
});

it('defaults happened_at to now when not provided', function () {
    $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'note',
            'body' => 'Just a quick note.',
        ])->assertRedirect();

    $narrative = BookingNarrative::query()->latest('id')->firstOrFail();
    expect($narrative->happened_at)->not->toBeNull()
        ->and($narrative->happened_at->diffInMinutes(now()))->toBeLessThan(1.0);
});

it('respects an explicit happened_at for back-filled entries', function () {
    $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'meeting',
            'body' => 'Site walk with client last Thursday.',
            'happened_at' => '2026-04-15 10:30:00',
        ])->assertRedirect();

    $narrative = BookingNarrative::query()->latest('id')->firstOrFail();
    expect($narrative->happened_at->toDateTimeString())
        ->toBe('2026-04-15 10:30:00');
});

it('refuses a body that is empty', function () {
    $response = $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'note',
            'body' => '',
        ]);

    $response->assertSessionHasErrors('body');
    expect(BookingNarrative::query()->count())->toBe(0);
});

it('refuses an unknown kind', function () {
    $response = $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'invented-kind',
            'body' => 'Hi.',
        ]);

    $response->assertSessionHasErrors('kind');
    expect(BookingNarrative::query()->count())->toBe(0);
});

it('caps body length at 5000 characters', function () {
    $response = $this->actingAs($this->user)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'note',
            'body' => str_repeat('x', 5001),
        ]);

    $response->assertSessionHasErrors('body');
    expect(BookingNarrative::query()->count())->toBe(0);
});

it('requires authentication', function () {
    $response = $this->post("/bookings/{$this->booking->id}/narratives", [
        'kind' => 'note',
        'body' => 'Unauthed.',
    ]);

    $response->assertRedirect('/login');
    expect(BookingNarrative::query()->count())->toBe(0);
});

it('exposes narratives on the booking show page newest-first', function () {
    BookingNarrative::factory()->create([
        'booking_id' => $this->booking->id,
        'author_user_id' => $this->user->id,
        'kind' => 'note',
        'body' => 'Older entry.',
        'happened_at' => now()->subDays(10),
    ]);
    BookingNarrative::factory()->create([
        'booking_id' => $this->booking->id,
        'author_user_id' => $this->user->id,
        'kind' => 'decision',
        'body' => 'Newer entry.',
        'happened_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($this->user)
        ->get("/bookings/{$this->booking->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('bookings/show')
        ->where('narratives.0.body', 'Newer entry.')
        ->where('narratives.1.body', 'Older entry.'));
});

it('exposes the narrative_kinds catalog on the show page', function () {
    $response = $this->actingAs($this->user)
        ->get("/bookings/{$this->booking->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('bookings/show')
        ->where('narrative_kinds.0.value', 'note')
        ->where('narrative_kinds.0.label', 'Note'));
});

it('stamps the authenticated user as author', function () {
    $other = grantSuperAdmin();

    $this->actingAs($other)
        ->post("/bookings/{$this->booking->id}/narratives", [
            'kind' => 'note',
            'body' => 'Attributed to other.',
        ])->assertRedirect();

    $narrative = BookingNarrative::query()->latest('id')->firstOrFail();
    expect($narrative->author_user_id)->toBe($other->id);
});

it('exposes the kind label on the show payload', function () {
    BookingNarrative::factory()->create([
        'booking_id' => $this->booking->id,
        'kind' => BookingNarrativeKind::SiteVisit->value,
        'body' => 'Walked the floor with client.',
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$this->booking->id}")
        ->assertInertia(fn ($page) => $page
            ->component('bookings/show')
            ->where('narratives.0.kind', 'site_visit')
            ->where('narratives.0.kind_label', 'Site visit'));
});
