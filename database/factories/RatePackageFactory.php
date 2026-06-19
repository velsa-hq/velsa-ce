<?php

namespace Database\Factories;

use App\Enums\RateCardKind;
use App\Models\RatePackage;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePackage>
 */
class RatePackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => fake()->randomElement(['Wedding Package', 'Conference Day Bundle', 'Trade Show Starter', 'Gala Package']),
            'kind' => fake()->randomElement(RateCardKind::cases())->value,
            'currency' => 'USD',
            'price_cents' => fake()->randomElement([250_000, 500_000, 1_000_000]),
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
