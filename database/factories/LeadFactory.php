<?php

namespace Database\Factories;

use App\Enums\LeadStage;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stage = fake()->randomElement(LeadStage::cases());

        return [
            'client_id' => Client::factory(),
            'venue_id' => Venue::factory(),
            'owner_user_id' => User::factory(),
            'name' => fake()->randomElement(['Wedding', 'Trade Show', 'Conference', 'Festival', 'Concert', 'Banquet', 'Expo', 'Fundraiser']).' - '.fake()->year(),
            'stage' => $stage->value,
            'estimated_value_cents' => fake()->numberBetween(500_000, 50_000_000),
            'probability' => $stage->defaultProbability(),
            'expected_close_date' => fake()->dateTimeBetween('-2 months', '+6 months')->format('Y-m-d'),
            'source' => fake()->randomElement(['referral', 'website', 'event', 'cold_outreach', 'partner']),
            'lost_reason' => $stage === LeadStage::Lost ? fake()->randomElement(['budget', 'timing', 'competition', 'fit']) : null,
            'notes' => fake()->optional()->paragraph(),
        ];
    }

    public function atStage(LeadStage $stage): static
    {
        return $this->state(fn () => [
            'stage' => $stage->value,
            'probability' => $stage->defaultProbability(),
            'lost_reason' => $stage === LeadStage::Lost ? fake()->randomElement(['budget', 'timing', 'competition', 'fit']) : null,
        ]);
    }
}
