<?php

use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = grantSuperAdmin();
    $this->category = EquipmentCategory::factory()->create();
});

it('renders the catalog editor', function () {
    $this->actingAs($this->admin)
        ->get('/admin/equipment-items')
        ->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('admin/equipment-items/index')->has('categories'));
});

it('creates a catalog item with standard + advance price (dollars -> cents)', function () {
    $this->actingAs($this->admin)->post('/admin/equipment-items', [
        'equipment_category_id' => $this->category->id,
        'sku' => 'TBL-6',
        'name' => '6ft Table',
        'unit_label' => 'each',
        'unit_price' => '65',
        'advance_price' => '50',
    ])->assertRedirect();

    $this->assertDatabaseHas('equipment_items', [
        'sku' => 'TBL-6', 'unit_price_cents' => 6500, 'advance_price_cents' => 5000,
    ]);
});

it('updates an item and leaves advance price optional', function () {
    $item = EquipmentItem::factory()->create(['equipment_category_id' => $this->category->id, 'unit_price_cents' => 1000]);

    $this->actingAs($this->admin)->put("/admin/equipment-items/{$item->sku}", [
        'equipment_category_id' => $this->category->id,
        'sku' => $item->sku,
        'name' => 'Renamed',
        'unit_label' => 'day',
        'unit_price' => '20',
    ])->assertRedirect();

    expect($item->fresh()->name)->toBe('Renamed')
        ->and($item->fresh()->unit_price_cents)->toBe(2000)
        ->and($item->fresh()->advance_price_cents)->toBeNull();
});

it('toggles active state', function () {
    $item = EquipmentItem::factory()->create(['equipment_category_id' => $this->category->id, 'is_active' => true]);

    $this->actingAs($this->admin)->patch("/admin/equipment-items/{$item->sku}/toggle")->assertRedirect();
    expect($item->fresh()->is_active)->toBeFalse();
});

it('forbids a non-admin', function () {
    $user = User::factory()->create();
    $user->assignRoleAt(Venue::factory()->create(), 'sales_rep');

    $this->actingAs($user)->get('/admin/equipment-items')->assertForbidden();
});
