<?php

namespace Database\Factories;

use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentItem>
 */
class EquipmentItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'equipment_category_id' => EquipmentCategory::factory(),
            'sku' => Str::upper(Str::random(8)),
            'name' => Str::title($name),
            'description' => null,
            'unit_label' => 'each',
            'unit_price_cents' => fake()->numberBetween(500, 50_000),
            'debit_account_code' => null,
            'credit_account_code' => null,
            'tax_rate' => null,
            'is_active' => true,
        ];
    }
}
