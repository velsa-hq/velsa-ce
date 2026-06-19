<?php

namespace Database\Factories;

use App\Models\Blackout;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Blackout>
 */
class BlackoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(1, 90));

        return [
            'blackoutable_type' => Venue::class,
            'blackoutable_id' => Venue::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addDays(fake()->numberBetween(1, 5)),
            'reason' => fake()->randomElement([
                'HVAC maintenance',
                'Carpet replacement',
                'Annual deep clean',
                'County training event',
                'Health-department closure',
            ]),
            'created_by_user_id' => null,
        ];
    }

    public function forVenue(Venue $venue): self
    {
        return $this->state(fn () => [
            'blackoutable_type' => Venue::class,
            'blackoutable_id' => $venue->id,
        ]);
    }

    public function forSpace(Space $space): self
    {
        return $this->state(fn () => [
            'blackoutable_type' => Space::class,
            'blackoutable_id' => $space->id,
        ]);
    }
}
