<?php

namespace Database\Factories;

use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exhibitor>
 */
class ExhibitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exhibitor_event_id' => ExhibitorEvent::factory(),
            'company_name' => fake()->company(),
            'contact_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'booth_assignment' => null,
            'booth_size' => '10x10',
            'address_json' => ['city' => fake()->city(), 'state' => 'FL'],
        ];
    }
}
