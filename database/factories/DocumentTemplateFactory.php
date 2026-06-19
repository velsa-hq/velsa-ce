<?php

namespace Database\Factories;

use App\Enums\TemplateKind;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => null,
            'kind' => TemplateKind::Contract->value,
            'name' => 'Standard '.fake()->word().' Template',
            'version' => 1,
            'body_html' => '<h1>{{booking.name}}</h1><p>This agreement is between {{venue.name}} and {{client.name}}.</p>',
            'variables_json' => null,
            'is_active' => true,
        ];
    }
}
