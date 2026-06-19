<?php

namespace Database\Factories;

use App\Models\BrandingImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandingImage>
 */
class BrandingImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->words(3, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
