<?php

namespace Database\Factories;

use App\Enums\ExhibitorPermitStatus;
use App\Enums\ExhibitorPermitType;
use App\Models\Exhibitor;
use App\Models\ExhibitorPermit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExhibitorPermit>
 */
class ExhibitorPermitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exhibitor_id' => Exhibitor::factory(),
            'permit_type' => fake()->randomElement(ExhibitorPermitType::cases()),
            'details' => fake()->sentence(12),
            'status' => ExhibitorPermitStatus::Pending,
            'submitted_via_portal' => true,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ExhibitorPermitStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn () => [
            'status' => ExhibitorPermitStatus::Denied,
            'reviewed_at' => now(),
        ]);
    }

    public function forExhibitor(Exhibitor $exhibitor): static
    {
        return $this->state(fn () => ['exhibitor_id' => $exhibitor->id]);
    }
}
