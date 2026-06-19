<?php

namespace Database\Factories;

use App\Enums\ExhibitorOrderStatus;
use App\Models\Exhibitor;
use App\Models\ExhibitorOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExhibitorOrder>
 */
class ExhibitorOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(20_000, 150_000);
        $tax = (int) round($subtotal * 0.07);

        return [
            'exhibitor_id' => Exhibitor::factory(),
            'order_number' => null, // auto-generated
            'status' => ExhibitorOrderStatus::Pending->value,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
            'paid_cents' => 0,
            'placed_at' => now()->subDays(fake()->numberBetween(0, 30)),
        ];
    }
}
