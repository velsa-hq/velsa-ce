<?php

namespace Database\Seeders;

use App\Enums\FundType;
use App\Models\Fund;
use Illuminate\Database\Seeder;

/**
 * Starter set of funds (general, tourism, enterprise). Idempotent on code.
 */
class FundsSeeder extends Seeder
{
    public function run(): void
    {
        $funds = [
            [
                'code' => 'GENERAL',
                'name' => 'General Fund',
                'fund_type' => FundType::General->value,
                'description' => 'Countywide general operations.',
            ],
            [
                'code' => 'TOURISM',
                'name' => 'Tourism Development Fund',
                'fund_type' => FundType::SpecialRevenue->value,
                'description' => 'Bed-tax-funded; convention center revenue and tourism-promotion spend.',
            ],
            [
                'code' => 'ENTERPRISE',
                'name' => 'Enterprise Operations Fund',
                'fund_type' => FundType::Enterprise->value,
                'description' => 'Self-supporting services (RV camping, stall rentals, equipment).',
            ],
        ];

        foreach ($funds as $row) {
            Fund::query()->updateOrCreate(
                ['code' => $row['code']],
                $row,
            );
        }
    }
}
