<?php

namespace Database\Factories;

use App\Models\EventOutline;
use App\Models\OutlineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutlineItem>
 */
class OutlineItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_outline_id' => EventOutline::factory(),
            'scheduled_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'department' => fake()->randomElement([
                'setup', 'av', 'catering', 'security', 'cleaning',
                'parking', 'reception', 'teardown', 'ops_lead',
            ]),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
