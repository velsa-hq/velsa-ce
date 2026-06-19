<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * Seeds the baseline operations departments, marked is_system so the
 * admin UI protects them from deletion. Runs before the outline
 * seeders, which reference these keys.
 */
class DepartmentSeeder extends Seeder
{
    /**
     * @var list<array{key: string, label: string, color: string, default_role?: string}>
     */
    private const DEFAULTS = [
        ['key' => 'setup', 'label' => 'Setup', 'color' => 'blue', 'default_role' => 'ops_lead'],
        ['key' => 'av', 'label' => 'A/V', 'color' => 'indigo', 'default_role' => 'ops_lead'],
        ['key' => 'catering', 'label' => 'Catering', 'color' => 'amber', 'default_role' => 'event_coordinator'],
        ['key' => 'security', 'label' => 'Security', 'color' => 'rose'],
        ['key' => 'cleaning', 'label' => 'Cleaning', 'color' => 'emerald'],
        ['key' => 'parking', 'label' => 'Parking', 'color' => 'sky'],
        ['key' => 'reception', 'label' => 'Reception', 'color' => 'fuchsia'],
        ['key' => 'teardown', 'label' => 'Teardown', 'color' => 'orange'],
        ['key' => 'ops_lead', 'label' => 'Ops lead', 'color' => 'purple'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => $dept) {
            Department::query()->updateOrCreate(
                ['key' => $dept['key']],
                [
                    'label' => $dept['label'],
                    'color' => $dept['color'],
                    'default_role' => $dept['default_role'] ?? null,
                    'sort_order' => $i,
                    'is_active' => true,
                    'is_system' => true,
                ],
            );
        }
    }
}
