<?php

namespace Database\Factories;

use App\Enums\FundType;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Fund>
 */
class FundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::upper(Str::slug($name, '_')),
            'name' => Str::title($name).' Fund',
            'fund_type' => FundType::General->value,
            'description' => null,
            'parent_fund_id' => null,
            'active_from' => null,
            'active_to' => null,
        ];
    }

    public function ofType(FundType $type): static
    {
        return $this->state(fn () => ['fund_type' => $type->value]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['active_to' => now()->subDay()->toDateString()]);
    }
}
