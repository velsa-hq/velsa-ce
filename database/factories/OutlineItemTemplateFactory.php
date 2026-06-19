<?php

namespace Database\Factories;

use App\Models\OutlineItemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutlineItemTemplate>
 */
class OutlineItemTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => ucfirst($this->faker->unique()->words(2, true)),
            'department' => 'setup',
            'default_duration_minutes' => $this->faker->randomElement([15, 30, 45, 60]),
            'description' => $this->faker->optional()->sentence(),
            'checklist' => [],
            'sort_order' => 0,
            'is_active' => true,
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
