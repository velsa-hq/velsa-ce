<?php

namespace Database\Factories;

use App\Models\SalesGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesGoal>
 */
class SalesGoalFactory extends Factory
{
    protected $model = SalesGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => (int) now()->year,
            'month' => null,
            'target_cents' => $this->faker->numberBetween(50, 500) * 100_000,
        ];
    }
}
