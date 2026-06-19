<?php

namespace Database\Factories;

use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\Venue;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'reference' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'kind' => fake()->randomElement(WorkOrderKind::cases())->value,
            'status' => WorkOrderStatus::Open->value,
            'priority' => 3,
            'scheduled_for' => now()->addDays(fake()->numberBetween(-14, 30)),
            'cost_cents' => fake()->numberBetween(0, 50_000),
        ];
    }

    public function withStatus(WorkOrderStatus $status): static
    {
        return $this->state(fn () => ['status' => $status->value]);
    }
}
