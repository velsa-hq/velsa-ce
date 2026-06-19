<?php

namespace Database\Factories;

use App\Models\FiscalYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalYear>
 */
class FiscalYearFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Oct 1 -> Sep 30 cycle
        $startYear = fake()->unique()->numberBetween(2020, 2099);
        $starts = "{$startYear}-10-01";
        $ends = ($startYear + 1).'-09-30';
        $shortYear = substr((string) ($startYear + 1), -2);

        return [
            'label' => "FY{$shortYear}",
            'starts_on' => $starts,
            'ends_on' => $ends,
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'closed_at' => now()->subDays(7),
        ]);
    }

    public function current(): static
    {
        // window containing today
        return $this->state(fn () => [
            'label' => 'FY'.now()->format('y'),
            'starts_on' => now()->subMonths(6)->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
        ]);
    }
}
