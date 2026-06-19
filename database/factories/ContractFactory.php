<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'template_id' => null,
            'parent_contract_id' => null,
            'reference' => null, // auto-generated in model creating hook
            'kind' => 'contract',
            'status' => ContractStatus::Draft->value,
            'total_cents' => fake()->numberBetween(50_000, 5_000_000),
            'rendered_html' => '<p>Sample contract body.</p>',
            'provider' => 'docusign',
        ];
    }

    public function inStatus(ContractStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status->value,
            'sent_at' => $status === ContractStatus::Sent ? now()->subDay() : null,
            'viewed_at' => in_array($status, [ContractStatus::Viewed, ContractStatus::PartiallySigned, ContractStatus::Signed], true) ? now()->subHours(12) : null,
            'signed_at' => $status === ContractStatus::Signed ? now()->subHours(6) : null,
            'provider_envelope_id' => $status !== ContractStatus::Draft ? 'env_'.fake()->uuid() : null,
        ]);
    }
}
