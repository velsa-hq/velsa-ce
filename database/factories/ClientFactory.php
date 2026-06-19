<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => fake()->randomElement(ClientType::cases())->value,
            'industry' => fake()->randomElement(['Tourism', 'Tech', 'Education', 'Healthcare', 'Non-Profit', 'Manufacturing', 'Retail']),
            'source' => fake()->randomElement(['referral', 'website', 'event', 'cold_outreach', 'partner']),
            'address_json' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => 'FL',
                'postal_code' => fake()->postcode(),
            ],
            'tax_id_encrypted' => fake()->numerify('##-#######'),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'name' => fake()->name(),
            'type' => ClientType::Individual->value,
            'industry' => null,
        ]);
    }
}
