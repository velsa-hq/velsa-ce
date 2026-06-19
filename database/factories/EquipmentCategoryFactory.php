<?php

namespace Database\Factories;

use App\Models\EquipmentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EquipmentCategory>
 */
class EquipmentCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'code' => Str::upper(Str::slug($name, '_')),
            'name' => Str::title($name),
            'description' => null,
            'department' => fake()->randomElement(['av', 'catering', 'setup', 'ops']),
            'debit_account_code' => '1100',
            'credit_account_code' => '4300',
            'tax_rate' => 0.07,
            'is_active' => true,
        ];
    }
}
