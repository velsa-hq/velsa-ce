<?php

namespace Database\Factories;

use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExhibitorOrderItem>
 */
class ExhibitorOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 6);
        $unit = fake()->numberBetween(2_500, 25_000);

        return [
            'exhibitor_order_id' => ExhibitorOrder::factory(),
            'sku' => strtoupper(fake()->bothify('???-####')),
            'name' => fake()->randomElement(['10x10 Booth', '10x20 Booth', 'Electricity', '6\' Table', 'Chairs (4)', 'Wifi Access', 'Power Strip']),
            'department' => fake()->randomElement(['booths', 'av', 'utilities', 'furniture']),
            'gl_account' => fake()->numerify('4###'),
            'quantity' => $qty,
            'unit_price_cents' => $unit,
            'line_total_cents' => $qty * $unit,
        ];
    }
}
