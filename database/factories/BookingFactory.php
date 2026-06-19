<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+6 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(2, 48).' hours');

        return [
            'venue_id' => Venue::factory(),
            'client_id' => Client::factory(),
            'lead_id' => null,
            'owner_user_id' => null,
            'reference' => null, // auto-generated in model creating hook
            'name' => fake()->randomElement(['Annual Gala', 'Trade Show', 'Wedding', 'Conference', 'Banquet']).' - '.fake()->year(),
            'kind' => fake()->randomElement(['wedding', 'trade_show', 'conference', 'banquet', 'concert', 'fundraiser']),
            'status' => BookingStatus::Inquiry->value,
            'start_at' => $start,
            'end_at' => $end,
            'total_cents' => fake()->numberBetween(50_000, 5_000_000),
            'attendance_estimate' => fake()->numberBetween(50, 1000),
        ];
    }

    public function definite(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Definite->value]);
    }

    public function tentative(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Tentative->value]);
    }

    public function withStatus(BookingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
