<?php

namespace Database\Seeders;

use App\Models\OutlineItemTemplate;
use Illuminate\Database\Seeder;

/**
 * Reusable run-of-show item templates. Marked is_system (admin UI
 * blocks deletion, allows hide/edit). Run after DepartmentSeeder; the
 * `department` keys reference it.
 */
class OutlineItemTemplateSeeder extends Seeder
{
    /**
     * @var list<array{label:string, department:string, default_duration_minutes:int, description:?string, checklist:list<string>}>
     */
    private const DEFAULTS = [
        [
            'label' => 'A/V sound check',
            'department' => 'av',
            'default_duration_minutes' => 30,
            'description' => "Full audio pass before doors. Confirm every input and the room mix.\n\nFlag anything broken to the **Ops lead** immediately.",
            'checklist' => [
                'Power on console + amps, confirm signal',
                'Mic check - handheld, lavalier, podium',
                'Playback test from house laptop + presenter laptop',
                'Set monitor + house levels, ring out feedback',
                'Confirm recording / stream feed (if applicable)',
            ],
        ],
        [
            'label' => 'Crew setup - tables, chairs, linens',
            'department' => 'setup',
            'default_duration_minutes' => 90,
            'description' => 'Room flip to the approved floor plan. Cross-check against the diagram.',
            'checklist' => [
                'Pull floor plan / diagram for the room',
                'Set tables to plan, confirm counts',
                'Chairs + spacing per layout',
                'Linens + skirting',
                'Walk the room with the lead, fix gaps',
            ],
        ],
        [
            'label' => 'Catering load-in',
            'department' => 'catering',
            'default_duration_minutes' => 45,
            'description' => 'Receive the caterer and stage service.',
            'checklist' => [
                'Confirm dock + access with caterer',
                'Stage chafing dishes / warmers, fire sternos',
                'Bar + beverage station set',
                'Verify head count vs guarantee',
            ],
        ],
        [
            'label' => 'Pre-event ops huddle',
            'department' => 'ops_lead',
            'default_duration_minutes' => 15,
            'description' => 'Quick all-hands before doors - assignments, timing, radios.',
            'checklist' => [
                'Radios distributed + channel check',
                'Walk the run-of-show with leads',
                'Confirm emergency + first-aid points',
            ],
        ],
        [
            'label' => 'Teardown + venue walkthrough',
            'department' => 'teardown',
            'default_duration_minutes' => 60,
            'description' => 'Strike and final walk with the client.',
            'checklist' => [
                'Strike AV + cabling, coil + store',
                'Break down tables / chairs / linens',
                'Lost & found sweep',
                'Final walkthrough + damage check with client',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => $template) {
            OutlineItemTemplate::query()->updateOrCreate(
                ['label' => $template['label']],
                [
                    'department' => $template['department'],
                    'default_duration_minutes' => $template['default_duration_minutes'],
                    'description' => $template['description'],
                    'checklist' => $template['checklist'],
                    'sort_order' => $i,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
