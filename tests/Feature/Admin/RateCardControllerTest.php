<?php

use App\Models\RateCard;
use App\Models\Space;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('lists rate cards for a pricing admin', function () {
    $admin = grantSuperAdmin();
    RateCard::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/admin/rate-cards')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/rate-cards/index')->has('cards', 2));
});

it('creates a rate card with a space rental entry (dollars to cents)', function () {
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);

    $this->actingAs($admin)->post('/admin/rate-cards', [
        'venue_id' => $venue->id,
        'name' => 'Standard 2026',
        'kind' => 'standard',
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'entries' => [
            ['kind' => 'space', 'space_id' => $space->id, 'unit' => 'daily', 'rate' => '1200.50', 'min_charge' => '500', 'included_hours' => 8],
        ],
    ])->assertRedirect();

    $card = RateCard::with('entries')->sole();
    expect($card->name)->toBe('Standard 2026')
        ->and($card->entries)->toHaveCount(1)
        ->and($card->entries[0]->space_id)->toBe($space->id)
        ->and($card->entries[0]->rate_cents)->toBe(120_050)
        ->and($card->entries[0]->min_charge_cents)->toBe(50_000)
        ->and($card->entries[0]->included_hours)->toBe(8);
});

it('replaces entries on update', function () {
    $admin = grantSuperAdmin();
    $venue = Venue::factory()->create();
    $space = Space::factory()->create(['venue_id' => $venue->id]);
    $card = RateCard::factory()->create(['venue_id' => $venue->id]);
    $card->entries()->create(['space_id' => $space->id, 'unit' => 'hourly', 'rate_cents' => 5000, 'min_charge_cents' => 0]);

    $this->actingAs($admin)->put("/admin/rate-cards/{$card->id}", [
        'venue_id' => $venue->id,
        'name' => 'Updated',
        'kind' => 'nonprofit',
        'effective_from' => '2026-01-01',
        'is_active' => true,
        'entries' => [
            ['kind' => 'space', 'space_id' => $space->id, 'unit' => 'daily', 'rate' => '99', 'min_charge' => '0'],
        ],
    ])->assertRedirect();

    $card->refresh()->load('entries');
    expect($card->name)->toBe('Updated')
        ->and($card->kind->value)->toBe('nonprofit')
        ->and($card->entries)->toHaveCount(1)
        ->and($card->entries[0]->rate_cents)->toBe(9_900);
});

it('deletes a rate card', function () {
    $admin = grantSuperAdmin();
    $card = RateCard::factory()->create();

    $this->actingAs($admin)->delete("/admin/rate-cards/{$card->id}")->assertRedirect();
    expect(RateCard::find($card->id))->toBeNull();
});

it('forbids a non-pricing user', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/rate-cards')->assertForbidden();
});
