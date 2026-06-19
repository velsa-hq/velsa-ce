<?php

namespace Database\Factories;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => fake()->randomElement(SupportRequestCategory::cases()),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'page_url' => '/'.fake()->slug(2),
            'app_version' => '1.3.0',
            'status' => SupportRequestStatus::Open,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SupportRequestStatus::Closed,
            'resolved_at' => now(),
        ]);
    }
}
