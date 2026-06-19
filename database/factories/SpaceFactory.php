<?php

namespace Database\Factories;

use App\Enums\BookableUnit;
use App\Models\Space;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    /**
     * seeded system kind slugs, duplicated so the factory doesn't need space_kinds seeded
     *
     * @var list<string>
     */
    private const DEFAULT_KINDS = [
        'room', 'ballroom', 'outdoor_field', 'arena', 'stall',
        'rv_pad', 'cabin', 'barn', 'terrace', 'zone',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'parent_space_id' => null,
            'name' => fake()->words(2, true),
            'kind' => fake()->randomElement(self::DEFAULT_KINDS),
            'capacity' => fake()->numberBetween(20, 1000),
            'sqft' => fake()->numberBetween(200, 25000),
            'dimensions_json' => null,
            'bookable_unit' => BookableUnit::Daily->value,
            'attributes_json' => null,
            'retired_at' => null,
        ];
    }

    public function ofKind(string $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }

    public function childOf(Space $parent): static
    {
        return $this->state(fn () => [
            'venue_id' => $parent->venue_id,
            'parent_space_id' => $parent->id,
        ]);
    }
}
