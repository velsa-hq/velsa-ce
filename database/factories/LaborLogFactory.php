<?php

namespace Database\Factories;

use App\Models\LaborLog;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaborLog>
 */
class LaborLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDays(fake()->numberBetween(0, 30));

        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => User::factory(),
            'started_at' => $start,
            'ended_at' => $start->copy()->addMinutes(fake()->numberBetween(15, 240)),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
