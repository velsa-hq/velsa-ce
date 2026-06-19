<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Venue';

        return [
            'name' => $name,
            'slug' => null,
            'address_json' => [
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'state' => 'FL',
                'postal_code' => fake()->postcode(),
            ],
            'timezone' => 'America/Chicago',
            'settings_json' => null,
            'active_at' => now()->subDays(fake()->numberBetween(30, 365)),
            'retired_at' => null,
        ];
    }

    public function comingSoon(): static
    {
        return $this->state(fn () => ['active_at' => null]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['retired_at' => now()]);
    }
}
