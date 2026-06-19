<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractSigner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractSigner>
 */
class ContractSignerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'signing_order' => 1,
            'role' => 'client',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
