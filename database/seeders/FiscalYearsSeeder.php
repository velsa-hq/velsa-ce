<?php

namespace Database\Seeders;

use App\Models\FiscalYear;
use Illuminate\Database\Seeder;

/**
 * Current and next fiscal year for an Oct 1 -> Sep 30 cycle.
 * Idempotent on label; dates are a default and can be overridden.
 */
class FiscalYearsSeeder extends Seeder
{
    public function run(): void
    {
        $today = now();

        // Oct-Dec: FY started this year; Jan-Sep: FY started last year
        $currentFyStartYear = (int) $today->format('m') >= 10
            ? (int) $today->format('Y')
            : (int) $today->format('Y') - 1;

        $years = [$currentFyStartYear, $currentFyStartYear + 1];

        foreach ($years as $startYear) {
            $endYear = $startYear + 1;
            $shortEnd = substr((string) $endYear, -2);
            FiscalYear::query()->updateOrCreate(
                ['label' => "FY{$shortEnd}"],
                [
                    'starts_on' => "{$startYear}-10-01",
                    'ends_on' => "{$endYear}-09-30",
                    'is_closed' => false,
                ],
            );
        }
    }
}
