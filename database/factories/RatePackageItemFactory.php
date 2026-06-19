<?php

namespace Database\Factories;

use App\Models\RatePackage;
use App\Models\RatePackageItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePackageItem>
 */
class RatePackageItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_package_id' => RatePackage::factory(),
            'space_id' => null,
            'equipment_sku' => null,
            'label' => fake()->randomElement(['Ballroom rental', 'AV package', 'Setup & teardown', 'Catering coordination']),
            'quantity' => 1,
            'unit' => null,
            'notes' => null,
        ];
    }
}
