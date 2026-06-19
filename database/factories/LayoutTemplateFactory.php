<?php

namespace Database\Factories;

use App\Models\LayoutTemplate;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LayoutTemplate>
 */
class LayoutTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $objects = [
            ['id' => 'tpl_obj_1', 'type' => 'round_table_60', 'x' => 100, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
            ['id' => 'tpl_obj_2', 'type' => 'round_table_60', 'x' => 200, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
        ];

        return [
            'space_id' => Space::factory(),
            'created_by_user_id' => null,
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['banquet', 'classroom', 'theater', 'booth_grid', 'u_shape']),
            'description' => fake()->sentence(),
            'objects_json' => $objects,
            'object_count' => count($objects),
            'seat_count' => 16,
        ];
    }

    public function global(): self
    {
        return $this->state(['space_id' => null]);
    }
}
