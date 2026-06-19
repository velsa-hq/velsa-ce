<?php

namespace Database\Factories;

use App\Models\ResourceInventory;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceInventory>
 */
class ResourceInventoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(10, 500);

        return [
            'venue_id' => Venue::factory(),
            'kind' => fake()->randomElement(['table', 'chair', 'staging', 'av', 'tent', 'fencing', 'generator']),
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'name' => fake()->words(2, true),
            'quantity_total' => $total,
            'quantity_available' => $total,
            'attributes_json' => null,
            'retired_at' => null,
        ];
    }
}
