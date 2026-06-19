<?php

namespace Database\Factories;

use App\Enums\DunningStage;
use App\Enums\InvoiceStatus;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(10_00, 5000_00);
        $tax = (int) round($subtotal * 0.07);
        $total = $subtotal + $tax;

        return [
            'number' => null,
            'invoiceable_type' => ExhibitorOrder::class,
            'invoiceable_id' => ExhibitorOrder::factory(),
            'status' => InvoiceStatus::Issued->value,
            'dunning_stage' => DunningStage::None->value,
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'paid_cents' => 0,
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
            'net_days' => 30,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $a) => [
            'status' => InvoiceStatus::Paid->value,
            'paid_cents' => $a['total_cents'],
            'paid_at' => now(),
        ]);
    }

    public function pastDue(int $daysOverdue = 14): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::PastDue->value,
            'due_on' => now()->subDays($daysOverdue)->toDateString(),
            'issued_on' => now()->subDays($daysOverdue + 30)->toDateString(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Draft->value,
            'issued_on' => null,
            'due_on' => null,
        ]);
    }
}
