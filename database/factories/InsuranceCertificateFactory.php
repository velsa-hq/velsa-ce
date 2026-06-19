<?php

namespace Database\Factories;

use App\Enums\InsuranceCertificateStatus;
use App\Enums\InsurancePolicyType;
use App\Models\Client;
use App\Models\Exhibitor;
use App\Models\InsuranceCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InsuranceCertificate>
 */
class InsuranceCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holder_type' => Client::class,
            'holder_id' => Client::factory(),
            'policy_type' => fake()->randomElement(InsurancePolicyType::cases()),
            'carrier' => fake()->company().' Insurance',
            'policy_number' => strtoupper(fake()->bothify('??-#######')),
            'coverage_amount_cents' => fake()->randomElement([100_000_000, 200_000_000, 500_000_000]),
            'effective_date' => now()->subMonths(2)->toDateString(),
            'expires_on' => now()->addMonths(10)->toDateString(),
            'status' => InsuranceCertificateStatus::Pending,
            'submitted_via_portal' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => InsuranceCertificateStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function expiringOn(string $date): static
    {
        return $this->state(fn () => [
            'status' => InsuranceCertificateStatus::Approved,
            'reviewed_at' => now(),
            'expires_on' => $date,
        ]);
    }

    public function forExhibitor(Exhibitor $exhibitor): static
    {
        return $this->state(fn () => [
            'holder_type' => Exhibitor::class,
            'holder_id' => $exhibitor->id,
        ]);
    }
}
