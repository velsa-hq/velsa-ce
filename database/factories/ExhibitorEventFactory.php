<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\ExhibitorEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExhibitorEvent>
 */
class ExhibitorEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'booking_id' => Booking::factory(),
            'name' => ucfirst($name).' Expo',
            'portal_slug' => Str::slug($name).'-'.fake()->randomNumber(4),
            'default_booth_size' => '10x10',
            'registration_opens_at' => now()->subDays(30),
            'registration_closes_at' => now()->addDays(30),
        ];
    }
}
