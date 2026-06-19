<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(AccountType::cases());

        return [
            // 5-digit code avoids collision with the 4-digit seeded chart;
            // fake()->unique() only dedupes within the factory
            'code' => (string) fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->words(2, true),
            'description' => null,
            'account_type' => $type->value,
            'account_subtype' => null,
            'normal_balance' => $type->normalBalance(),
            'parent_account_id' => null,
            'is_postable' => true,
            'active_from' => null,
            'active_to' => null,
        ];
    }

    public function ofType(AccountType $type): static
    {
        return $this->state(fn () => [
            'account_type' => $type->value,
            'normal_balance' => $type->normalBalance(),
        ]);
    }

    public function nonPostable(): static
    {
        return $this->state(fn () => ['is_postable' => false]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['active_to' => now()->subDay()->toDateString()]);
    }
}
