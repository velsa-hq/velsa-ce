<?php

namespace Database\Factories;

use App\Models\ReportDefinition;
use App\Reports\ReportDatasource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportDefinition>
 */
class ReportDefinitionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => null,
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'datasource' => ReportDatasource::Bookings->value,
            'filters_json' => [],
            'dimensions_json' => [],
            'metrics_json' => [],
            'sort_json' => [],
            'row_limit' => 1000,
            'created_by_user_id' => null,
        ];
    }
}
