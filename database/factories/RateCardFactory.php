<?php

namespace Database\Factories;

use App\Enums\RateCardKind;
use App\Models\RateCard;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCard>
 */
class RateCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => fake()->randomElement(['Standard 2026', 'Nonprofit Discount', 'Peak Season', 'Government Rate']),
            'kind' => fake()->randomElement(RateCardKind::cases())->value,
            'currency' => 'USD',
            'effective_from' => now()->startOfYear()->toDateString(),
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
