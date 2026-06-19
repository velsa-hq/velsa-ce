<?php

use App\Models\RatePackage;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists packages for a pricing admin', function () {
    $admin = grantSuperAdmin();
    RatePackage::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/admin/rate-packages')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/rate-packages/index')->has('packages', 2));
});

it('creates a package with mixed items at a bundle price', function () {
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    $this->actingAs($admin)->post('/admin/rate-packages', [
        'venue_id' => $venue->id,
        'name' => 'Wedding Package',
        'kind' => 'standard',
        'price' => '7500.00',
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'items' => [
            ['kind' => 'space', 'space_id' => $space->id, 'quantity' => 1, 'unit' => 'daily'],
            ['kind' => 'service', 'label' => 'Day-of coordination', 'quantity' => 1],
        ],
    ])->assertRedirect();

    $package = RatePackage::with('items')->sole();
    expect($package->name)->toBe('Wedding Package')
        ->and($package->price_cents)->toBe(750_000)
        ->and($package->items)->toHaveCount(2)
        ->and($package->items->firstWhere('space_id', $space->id))->not->toBeNull()
        ->and($package->items->firstWhere('label', 'Day-of coordination'))->not->toBeNull();
});

it('replaces items on update', function () {
    $admin = grantSuperAdmin();
    $package = RatePackage::factory()->create();
    $package->items()->create(['label' => 'Old item', 'quantity' => 1]);

    $this->actingAs($admin)->put("/admin/rate-packages/{$package->id}", [
        'venue_id' => $package->venue_id,
        'name' => $package->name,
        'kind' => 'government',
        'price' => '100',
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'items' => [
            ['kind' => 'service', 'label' => 'New item', 'quantity' => 2],
        ],
    ])->assertRedirect();

    $package->refresh()->load('items');
    expect($package->kind->value)->toBe('government')
        ->and($package->price_cents)->toBe(10_000)
        ->and($package->items)->toHaveCount(1)
        ->and($package->items[0]->label)->toBe('New item');
});

it('deletes a package', function () {
    $admin = grantSuperAdmin();
    $package = RatePackage::factory()->create();

    $this->actingAs($admin)->delete("/admin/rate-packages/{$package->id}")->assertRedirect();
    expect(RatePackage::find($package->id))->toBeNull();
});

it('forbids a non-pricing user', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/rate-packages')->assertForbidden();
});
