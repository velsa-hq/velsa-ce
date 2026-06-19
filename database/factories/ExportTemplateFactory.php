<?php

namespace Database\Factories;

use App\Enums\ExportFormat;
use App\Models\ExportTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportTemplate>
 */
class ExportTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'slug' => null,
            'name' => $name,
            'description' => fake()->optional()->sentence(),
            'format' => ExportFormat::Csv->value,
            'delimiter' => ',',
            'quote_char' => '"',
            'line_ending' => "\n",
            'encoding' => 'utf-8',
            'include_header' => true,
            'include_footer' => false,
            'is_default' => false,
            'file_extension' => 'csv',
            'created_by_user_id' => null,
        ];
    }

    public function csv(): static
    {
        return $this->state(fn () => [
            'format' => ExportFormat::Csv->value,
            'file_extension' => 'csv',
        ]);
    }

    public function fixedWidth(): static
    {
        // fixed-width layout is the contract, not labels, so no header
        return $this->state(fn () => [
            'format' => ExportFormat::FixedWidth->value,
            'file_extension' => 'txt',
            'include_header' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
