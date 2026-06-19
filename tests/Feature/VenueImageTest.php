<?php

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
});

it('uploads a venue photo and exposes its url', function () {
    Storage::fake('public');

    $this->actingAs($this->user)
        ->post('/venues', [
            'name' => 'Photo Venue',
            'timezone' => 'America/Chicago',
            'photo' => UploadedFile::fake()->image('v.jpg', 800, 600),
        ])
        ->assertRedirect();

    $venue = Venue::query()->where('name', 'Photo Venue')->first();
    expect($venue->hasUploadedPhoto())->toBeTrue()
        ->and($venue->imageUrl())->not->toBeNull();
});

it('falls back to a generated identity image when no photo is set', function () {
    $venue = Venue::factory()->create();

    expect($venue->hasUploadedPhoto())->toBeFalse()
        ->and($venue->imageUrl())->toContain("/identity/venue-{$venue->id}.svg")
        ->and($venue->thumbUrl())->toContain("/identity/venue-{$venue->id}.svg");
});

it('serves a deterministic svg for an identity seed', function () {
    $first = $this->get('/identity/venue-1.svg');
    $first->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');
    expect($first->getContent())->toContain('<svg')->toContain('<polygon');

    // same seed -> byte-identical, different seed -> different art
    expect($this->get('/identity/venue-1.svg')->getContent())->toBe($first->getContent())
        ->and($this->get('/identity/venue-2.svg')->getContent())->not->toBe($first->getContent());
});

it('removes a venue photo, reverting to the identity image', function () {
    Storage::fake('public');
    $venue = Venue::factory()->create();
    $venue->addMedia(UploadedFile::fake()->image('v.jpg'))->toMediaCollection('photo');

    $this->actingAs($this->user)
        ->put("/venues/{$venue->slug}", [
            'name' => $venue->name,
            'timezone' => $venue->timezone,
            'remove_image' => '1',
        ])
        ->assertRedirect();

    $fresh = $venue->fresh();
    expect($fresh->hasUploadedPhoto())->toBeFalse()
        ->and($fresh->imageUrl())->toContain("/identity/venue-{$fresh->id}.svg");
});

it('lets an uploaded photo replace the identity image', function () {
    Storage::fake('public');
    $venue = Venue::factory()->create();

    $this->actingAs($this->user)
        ->put("/venues/{$venue->slug}", [
            'name' => $venue->name,
            'timezone' => $venue->timezone,
            'photo' => UploadedFile::fake()->image('p.jpg'),
        ])
        ->assertRedirect();

    expect($venue->fresh()->hasUploadedPhoto())->toBeTrue();
});
