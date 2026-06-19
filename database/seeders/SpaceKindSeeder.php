<?php

namespace Database\Seeders;

use App\Models\SpaceKind;
use Illuminate\Database\Seeder;

/**
 * Baseline system space kinds, marked is_system so the admin UI protects
 * them from deletion. Must run before the venue seeders, which reference
 * these keys.
 */
class SpaceKindSeeder extends Seeder
{
    /**
     * @var list<array{key: string, label: string}>
     */
    private const DEFAULTS = [
        ['key' => 'room', 'label' => 'Room'],
        ['key' => 'ballroom', 'label' => 'Ballroom'],
        ['key' => 'outdoor_field', 'label' => 'Outdoor Field'],
        ['key' => 'arena', 'label' => 'Arena'],
        ['key' => 'stall', 'label' => 'Stall'],
        ['key' => 'rv_pad', 'label' => 'RV Pad'],
        ['key' => 'cabin', 'label' => 'Cabin'],
        ['key' => 'barn', 'label' => 'Barn'],
        ['key' => 'terrace', 'label' => 'Terrace'],
        ['key' => 'zone', 'label' => 'Zone'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => $kind) {
            SpaceKind::query()->updateOrCreate(
                ['key' => $kind['key']],
                [
                    'label' => $kind['label'],
                    'sort_order' => $i,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
