<?php

namespace Database\Factories;

use App\Enums\InventoryAction;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderItem>
 */
class WorkOrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'sku' => strtoupper(fake()->bothify('??-####')),
            'name' => fake()->randomElement(['Chairs', 'Tables', 'AC Filter', 'Light Bulbs', 'Cleaning Supplies']),
            'quantity' => fake()->numberBetween(1, 20),
            'unit' => 'each',
            'action' => fake()->randomElement(InventoryAction::cases())->value,
        ];
    }
}
