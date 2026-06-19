<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingSpace;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingSpace>
 */
class BookingSpaceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+6 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(2, 24).' hours');

        return [
            'booking_id' => Booking::factory(),
            'space_id' => Space::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'setup_minutes_before' => 60,
            'teardown_minutes_after' => 60,
            'rate_applied_cents' => fake()->numberBetween(20_000, 500_000),
        ];
    }
}
