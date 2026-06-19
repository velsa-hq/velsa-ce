<?php

namespace Database\Factories;

use App\Enums\BookableUnit;
use App\Models\RateCard;
use App\Models\RateCardEntry;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RateCardEntry>
 */
class RateCardEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rate_card_id' => RateCard::factory(),
            'space_id' => Space::factory(),
            'equipment_sku' => null,
            'unit' => BookableUnit::Daily->value,
            'rate_cents' => fake()->numberBetween(20_000, 500_000),
            'min_charge_cents' => 0,
            'included_hours' => null,
        ];
    }
}
