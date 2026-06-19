<?php

namespace Database\Factories;

use App\Models\ExportTemplate;
use App\Models\ExportTemplateColumn;
use App\Services\Accounting\ExportSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExportTemplateColumn>
 */
class ExportTemplateColumnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'export_template_id' => ExportTemplate::factory(),
            'sort_order' => 0,
            'label' => fake()->words(2, true),
            'source' => ExportSource::ACCOUNT_CODE,
            'format_mask' => null,
            'default_value' => null,
            'width' => null,
            'align' => 'left',
            'pad_char' => ' ',
        ];
    }
}
