<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAssignment>
 */
class StaffAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-7 days', '+30 days');
        $hours = fake()->numberBetween(4, 10);

        return [
            'booking_id' => Booking::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement([
                'Event lead', 'AV technician', 'Catering captain',
                'Security officer', 'Setup crew', 'Reception desk',
                'Parking attendant',
            ]),
            'start_at' => $start,
            'end_at' => (clone $start)->modify("+{$hours} hours"),
            'hourly_rate_cents' => fake()->numberBetween(2_000, 6_500),
            'notes' => null,
        ];
    }
}
