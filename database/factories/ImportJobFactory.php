<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\ImportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportJob>
 */
class ImportJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'clients',
            'status' => ImportStatus::Pending,
            'original_filename' => fake()->slug(2).'.csv',
            'disk' => 'local',
            'file_path' => 'imports/'.fake()->uuid().'.csv',
            'has_header' => true,
            'delimiter' => ',',
            'column_map' => null,
            'created_by_user_id' => null,
        ];
    }

    public function committed(): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Completed,
            'committed_at' => now(),
            'created_rows' => 1,
        ]);
    }
}
