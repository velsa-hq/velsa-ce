<?php

namespace Database\Factories;

use App\Models\AuditRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditRule>
 */
class AuditRuleFactory extends Factory
{
    protected $model = AuditRule::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'event_type' => $this->faker->randomElement(['role.', 'user.disabled', 'permission.', 'invoice.']),
            'description' => null,
            'is_active' => true,
        ];
    }
}
