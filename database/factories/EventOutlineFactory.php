<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\EventOutline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventOutline>
 */
class EventOutlineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'published_version' => 0,
            'published_at' => null,
            'notes' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_version' => 1,
            'published_at' => now(),
        ]);
    }
}
