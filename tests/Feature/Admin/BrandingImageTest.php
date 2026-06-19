<?php

use App\Models\BrandingImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists managed images for a settings admin', function () {
    BrandingImage::factory()->count(2)->create();

    $this->actingAs(grantSuperAdmin())
        ->get('/admin/branding-images')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p
            ->component('admin/branding-images/index')
            ->has('images', 2)
        );
});

it('uploads a new active background image', function () {
    Storage::fake('public');
    $admin = grantSuperAdmin();

    $this->actingAs($admin)->post('/admin/branding-images', [
        'label' => 'Coastline at dusk',
        'image' => UploadedFile::fake()->image('coast.webp', 1600, 900),
    ])->assertRedirect();

    $image = BrandingImage::sole();
    expect($image->label)->toBe('Coastline at dusk')
        ->and($image->is_active)->toBeTrue()
        ->and($image->getFirstMedia('image'))->not->toBeNull();
});

it('requires an image file on upload', function () {
    $this->actingAs(grantSuperAdmin())
        ->from('/admin/branding-images')
        ->post('/admin/branding-images', ['label' => 'No file'])
        ->assertSessionHasErrors('image');
});

it('toggles a background image active flag', function () {
    $image = BrandingImage::factory()->create(['is_active' => true]);

    $this->actingAs(grantSuperAdmin())
        ->put("/admin/branding-images/{$image->id}", ['label' => '', 'is_active' => false])
        ->assertRedirect();

    expect($image->refresh()->is_active)->toBeFalse();
});

it('removes a background image', function () {
    $image = BrandingImage::factory()->create();

    $this->actingAs(grantSuperAdmin())
        ->delete("/admin/branding-images/{$image->id}")
        ->assertRedirect();

    expect(BrandingImage::find($image->id))->toBeNull();
});

it('forbids a non-settings user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/branding-images')
        ->assertForbidden();
});

it('serves active images as the welcome background pool', function () {
    Storage::fake('public');

    $active = BrandingImage::factory()->create();
    $active->addMedia(UploadedFile::fake()->image('a.webp', 1600, 900))->toMediaCollection('image');
    $inactive = BrandingImage::factory()->inactive()->create();
    $inactive->addMedia(UploadedFile::fake()->image('b.webp', 1600, 900))->toMediaCollection('image');

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->has('stockBackgrounds', 1));
});
