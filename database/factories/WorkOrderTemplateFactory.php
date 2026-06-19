<?php

namespace Database\Factories;

use App\Enums\WorkOrderKind;
use App\Models\Venue;
use App\Models\WorkOrderTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderTemplate>
 */
class WorkOrderTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => fake()->randomElement(['Weekly HVAC check', 'Monthly fire-extinguisher inspection', 'Quarterly deep clean']),
            'kind' => WorkOrderKind::PreventiveMaintenance->value,
            'recurrence_rrule' => 'FREQ=WEEKLY;BYDAY=MO',
            'items_json' => null,
            'default_assignee_role' => 'ops_lead',
            'lookahead_days' => 14,
            'is_active' => true,
        ];
    }
}
