<?php

namespace Database\Factories;

use App\Models\InventoryKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InventoryKind>
 */
class InventoryKindFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = ucfirst($this->faker->unique()->word());

        return [
            'key' => Str::slug($label, '_'),
            'label' => $label,
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
