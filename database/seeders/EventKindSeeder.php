<?php

namespace Database\Seeders;

use App\Models\EventKind;
use Illuminate\Database\Seeder;

/**
 * Baseline event kinds. Marked is_system so the admin UI blocks deletion;
 * admins can add their own.
 */
class EventKindSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'wedding' => 'Wedding',
        'conference' => 'Conference',
        'trade_show' => 'Trade show',
        'expo' => 'Expo',
        'concert' => 'Concert',
        'banquet' => 'Banquet',
        'fundraiser' => 'Fundraiser',
        'festival' => 'Festival',
        'sports' => 'Sports / tournament',
        'celebration' => 'Celebration',
        'retreat' => 'Retreat',
        'networking' => 'Networking',
        'training' => 'Training',
        'competition' => 'Competition',
        'career_fair' => 'Career fair',
    ];

    public function run(): void
    {
        $i = 0;
        foreach (self::DEFAULTS as $key => $label) {
            EventKind::query()->updateOrCreate(
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
