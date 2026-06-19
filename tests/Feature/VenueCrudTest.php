<?php

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
});

it('creates a venue with metadata and a generated slug', function () {
    $this->actingAs($this->user)
        ->post('/venues', [
            'name' => 'Harbor Convention Center',
            'building' => 'Bldg C',
            'street' => '123 Bayshore Dr',
            'city' => 'Sentinel Bay',
            'state' => 'fl',
            'zip' => '32541',
            'phone' => '850-555-0100',
            'website' => 'https://harbor.example',
            'timezone' => 'America/Chicago',
            'summary' => 'Waterfront convention space.',
            'is_active' => true,
        ])
        ->assertRedirect();

    $venue = Venue::query()->where('name', 'Harbor Convention Center')->first();
    expect($venue)->not->toBeNull()
        ->and($venue->slug)->toBe('harbor-convention-center')
        ->and($venue->phone)->toBe('850-555-0100')
        ->and($venue->website)->toBe('https://harbor.example')
        ->and($venue->address_json['building'])->toBe('Bldg C')
        ->and($venue->address_json['street'])->toBe('123 Bayshore Dr')
        ->and($venue->address_json['state'])->toBe('FL')
        ->and($venue->address_json['zip'])->toBe('32541')
        ->and($venue->settings_json['summary'])->toBe('Waterfront convention space.')
        ->and($venue->isActive())->toBeTrue();
});

it('requires a name and timezone to create a venue', function () {
    $this->actingAs($this->user)
        ->post('/venues', ['name' => '', 'timezone' => ''])
        ->assertSessionHasErrors(['name', 'timezone']);
});

it('rejects a non-http(s) website', function () {
    $this->actingAs($this->user)
        ->post('/venues', [
            'name' => 'XSS Hall',
            'timezone' => 'America/Chicago',
            'website' => 'javascript:alert(document.cookie)',
        ])
        ->assertSessionHasErrors('website');

    expect(Venue::query()->where('name', 'XSS Hall')->exists())->toBeFalse();
});

it("updates a venue's metadata", function () {
    $venue = Venue::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->user)
        ->put("/venues/{$venue->slug}", [
            'name' => 'New Name',
            'street' => '9 New St',
            'city' => 'Gulfton',
            'state' => 'FL',
            'zip' => '33333',
            'phone' => '850-555-0199',
            'website' => 'https://new.example',
            'timezone' => 'America/New_York',
            'summary' => 'Updated.',
            'is_active' => true,
        ])
        ->assertRedirect();

    $fresh = $venue->fresh();
    expect($fresh->name)->toBe('New Name')
        ->and($fresh->phone)->toBe('850-555-0199')
        ->and($fresh->address_json['street'])->toBe('9 New St')
        ->and($fresh->timezone)->toBe('America/New_York');
});

it('soft-deletes a venue and hides it from the index', function () {
    $venue = Venue::factory()->create();

    $this->actingAs($this->user)
        ->delete("/venues/{$venue->slug}")
        ->assertRedirect('/venues');

    expect($venue->fresh()->trashed())->toBeTrue()
        ->and(Venue::query()->whereKey($venue->id)->exists())->toBeFalse()
        ->and(Venue::withTrashed()->whereKey($venue->id)->exists())->toBeTrue();
});

it('lists archived venues and restores them', function () {
    $venue = Venue::factory()->create(['name' => 'Retired Hall']);
    $venue->delete();

    $this->actingAs($this->user)
        ->get('/venues/archive')
        ->assertInertia(fn ($page) => $page
            ->component('venues/archive')
            ->where('venues.0.name', 'Retired Hall'));

    $this->actingAs($this->user)
        ->patch("/venues/{$venue->slug}/restore")
        ->assertRedirect();

    expect($venue->fresh()->trashed())->toBeFalse();
});
