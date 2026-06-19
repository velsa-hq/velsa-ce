<?php

namespace Database\Seeders;

use App\Models\InventoryKind;
use Illuminate\Database\Seeder;

/**
 * Baseline inventory kinds. Marked is_system so the admin UI blocks
 * deletion; admins can add their own. Must run before
 * ResourceInventorySeeder, which references these keys.
 */
class InventoryKindSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'chairs' => 'Chairs',
        'tables' => 'Tables',
        'linens' => 'Linens',
        'staging' => 'Staging',
        'av' => 'A/V',
        'power' => 'Power',
        'tents' => 'Tents',
        'barricade' => 'Barricade',
        'supplies' => 'Supplies',
    ];

    public function run(): void
    {
        $i = 0;
        foreach (self::DEFAULTS as $key => $label) {
            InventoryKind::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'sort_order' => $i++,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
