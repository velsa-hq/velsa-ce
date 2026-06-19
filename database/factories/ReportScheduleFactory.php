<?php

namespace Database\Factories;

use App\Models\ReportSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSchedule>
 */
class ReportScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_slug' => 'balance-sheet',
            'params_json' => [],
            'format' => 'pdf',
            'frequency' => 'daily',
            'day_of_week' => null,
            'day_of_month' => null,
            'hour' => 6,
            'recipients' => ['finance@example.test'],
            'is_active' => true,
            'last_run_at' => null,
        ];
    }
}
