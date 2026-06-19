<?php

use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use Database\Seeders\EquipmentCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(EquipmentCatalogSeeder::class);
});

it('seeds the six baseline equipment categories', function () {
    $codes = EquipmentCategory::query()->pluck('code')->all();

    expect($codes)->toContain('FURNITURE', 'AV', 'ELECTRICAL', 'BOOTH', 'CATERING', 'LABOR');
});

it('seeds equipment items linked to a category', function () {
    $item = EquipmentItem::query()->where('sku', 'CHAIR-FOLD')->firstOrFail();

    expect($item->category)->not->toBeNull()
        ->and($item->category?->code)->toBe('FURNITURE');
});

it('falls back to the category credit account when the item has no override', function () {
    $item = EquipmentItem::query()->where('sku', 'AV-PROJECTOR')->firstOrFail();

    expect($item->credit_account_code)->toBeNull()
        ->and($item->effectiveCreditAccountCode())->toBe('4500');
});

it('prefers an item-level credit account override over the category default', function () {
    $cat = EquipmentCategory::query()->where('code', 'FURNITURE')->firstOrFail();
    $item = EquipmentItem::factory()->create([
        'equipment_category_id' => $cat->id,
        'sku' => 'TEST-OVERRIDE',
        'credit_account_code' => '4900',
    ]);

    expect($item->effectiveCreditAccountCode())->toBe('4900');
});

it('returns zero tax rate when neither item nor category defines one', function () {
    $cat = EquipmentCategory::factory()->create(['code' => 'NOTAX', 'tax_rate' => 0]);
    $item = EquipmentItem::factory()->create([
        'equipment_category_id' => $cat->id,
        'tax_rate' => null,
    ]);

    expect($item->effectiveTaxRate())->toBe(0.0);
});

it('uses sku as the route key on EquipmentItem', function () {
    expect((new EquipmentItem)->getRouteKeyName())->toBe('sku');
});

it('uses code as the route key on EquipmentCategory', function () {
    expect((new EquipmentCategory)->getRouteKeyName())->toBe('code');
});
