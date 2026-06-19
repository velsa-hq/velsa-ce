<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExhibitorPayment>
 */
class ExhibitorPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exhibitor_order_id' => ExhibitorOrder::factory(),
            'provider' => 'bluepay',
            'provider_transaction_id' => 'bp_'.Str::uuid(),
            'status' => PaymentStatus::Captured->value,
            'amount_cents' => fake()->numberBetween(20_000, 200_000),
            'last4' => (string) fake()->numerify('####'),
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'idempotency_key' => Str::random(32),
            'processed_at' => now()->subDays(fake()->numberBetween(0, 14)),
        ];
    }
}
