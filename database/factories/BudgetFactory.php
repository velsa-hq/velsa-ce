<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fiscal_year_id' => FiscalYear::factory(),
            'chart_of_account_id' => ChartOfAccount::factory(),
            'fund_id' => null,
            'amount_cents' => fake()->numberBetween(1000_00, 1_000_000_00),
            'notes' => null,
        ];
    }

    public function forFund(Fund $fund): static
    {
        return $this->state(fn () => ['fund_id' => $fund->id]);
    }
}
