<?php

namespace Database\Factories;

use App\Enums\BookingNarrativeKind;
use App\Models\Booking;
use App\Models\BookingNarrative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingNarrative>
 */
class BookingNarrativeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'author_user_id' => null,
            'kind' => fake()->randomElement(BookingNarrativeKind::cases())->value,
            'body' => fake()->sentence(12),
            'happened_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
