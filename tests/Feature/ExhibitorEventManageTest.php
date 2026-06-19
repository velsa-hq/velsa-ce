<?php

use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->booking = Booking::factory()->create();
});

it('creates an exhibitor event with an auto-generated slug', function () {
    $this->actingAs($this->user)
        ->post('/exhibitor-events', [
            'name' => 'Spring Home & Garden Expo',
            'booking_id' => $this->booking->id,
            'default_booth_size' => '10x10',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('exhibitor_events', [
        'name' => 'Spring Home & Garden Expo',
        'booking_id' => $this->booking->id,
        'portal_slug' => 'spring-home-garden-expo',
    ]);
});

it('disambiguates a colliding slug', function () {
    ExhibitorEvent::factory()->create(['portal_slug' => 'fall-expo']);

    $this->actingAs($this->user)
        ->post('/exhibitor-events', [
            'name' => 'Fall Expo',
            'booking_id' => $this->booking->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('exhibitor_events', ['portal_slug' => 'fall-expo-2']);
});

it('requires a name and a valid booking', function () {
    $this->actingAs($this->user)
        ->post('/exhibitor-events', ['name' => '', 'booking_id' => 999999])
        ->assertSessionHasErrors(['name', 'booking_id']);
});

it('updates an event', function () {
    $event = ExhibitorEvent::factory()->create(['name' => 'Old', 'booking_id' => $this->booking->id]);

    $this->actingAs($this->user)
        ->patch("/exhibitor-events/{$event->id}", [
            'name' => 'New Name',
            'booking_id' => $this->booking->id,
            'default_booth_size' => '10x20',
        ])
        ->assertRedirect();

    expect($event->fresh())->name->toBe('New Name')->default_booth_size->toBe('10x20');
});

it('deletes an empty event', function () {
    $event = ExhibitorEvent::factory()->create(['booking_id' => $this->booking->id]);

    $this->actingAs($this->user)
        ->delete("/exhibitor-events/{$event->id}")
        ->assertRedirect('/exhibitors');

    $this->assertDatabaseMissing('exhibitor_events', ['id' => $event->id]);
});

it('blocks deleting an event that still has exhibitors', function () {
    $event = ExhibitorEvent::factory()->create(['booking_id' => $this->booking->id]);
    Exhibitor::factory()->for($event, 'event')->create();

    $this->actingAs($this->user)
        ->delete("/exhibitor-events/{$event->id}")
        ->assertSessionHas('toast.type', 'error');

    $this->assertDatabaseHas('exhibitor_events', ['id' => $event->id]);
});
