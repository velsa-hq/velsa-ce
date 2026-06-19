<?php

namespace Database\Seeders;

use App\Models\EquipmentCategory;
use App\Models\EquipmentItem;
use Illuminate\Database\Seeder;

/**
 * Seeds the exhibitor-order equipment catalog. Categories carry the
 * default GL coding (debit A/R 1100, credit a per-category revenue
 * account); items inherit unless overridden. Idempotent on category
 * code and item sku.
 */
class EquipmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // category code => attributes
        $categories = [
            'FURNITURE' => [
                'name' => 'Furniture',
                'department' => 'setup',
                'debit_account_code' => '1100',
                'credit_account_code' => '4300', // Exhibitor Revenue
                'tax_rate' => 0.07,
            ],
            'AV' => [
                'name' => 'A/V & Production',
                'department' => 'av',
                'debit_account_code' => '1100',
                'credit_account_code' => '4500', // AV & Equipment Rental Revenue
                'tax_rate' => 0.07,
            ],
            'ELECTRICAL' => [
                'name' => 'Electrical / Power',
                'department' => 'av',
                'debit_account_code' => '1100',
                'credit_account_code' => '4500',
                'tax_rate' => 0.07,
            ],
            'BOOTH' => [
                'name' => 'Booth Equipment',
                'department' => 'setup',
                'debit_account_code' => '1100',
                'credit_account_code' => '4300',
                'tax_rate' => 0.07,
            ],
            'CATERING' => [
                'name' => 'Catering & F&B',
                'department' => 'catering',
                'debit_account_code' => '1100',
                'credit_account_code' => '4400', // Catering & F&B Revenue
                'tax_rate' => 0.07,
            ],
            'LABOR' => [
                'name' => 'Labor / Services',
                'department' => 'ops',
                'debit_account_code' => '1100',
                'credit_account_code' => '4900', // Other / Misc Revenue
                'tax_rate' => 0,
            ],
        ];

        foreach ($categories as $code => $attrs) {
            EquipmentCategory::query()->updateOrCreate(
                ['code' => $code],
                array_merge($attrs, ['code' => $code]),
            );
        }

        // sku => attributes (category resolved by category_code key)
        $items = [
            // Furniture
            ['sku' => 'CHAIR-FOLD', 'category_code' => 'FURNITURE', 'name' => 'Folding chair', 'unit_label' => 'each', 'unit_price_cents' => 800],
            ['sku' => 'CHAIR-PADDED', 'category_code' => 'FURNITURE', 'name' => 'Padded conference chair', 'unit_label' => 'each', 'unit_price_cents' => 1500],
            ['sku' => 'TABLE-6FT', 'category_code' => 'FURNITURE', 'name' => '6ft rectangular table', 'unit_label' => 'each', 'unit_price_cents' => 2200],
            ['sku' => 'TABLE-8FT', 'category_code' => 'FURNITURE', 'name' => '8ft rectangular table', 'unit_label' => 'each', 'unit_price_cents' => 2800],
            ['sku' => 'TABLE-ROUND-60', 'category_code' => 'FURNITURE', 'name' => '60" round table (seats 8)', 'unit_label' => 'each', 'unit_price_cents' => 3200],
            ['sku' => 'TABLE-COCKTAIL', 'category_code' => 'FURNITURE', 'name' => 'Cocktail high-top table', 'unit_label' => 'each', 'unit_price_cents' => 2500],
            ['sku' => 'LINEN-90', 'category_code' => 'FURNITURE', 'name' => '90" linen tablecloth', 'unit_label' => 'each', 'unit_price_cents' => 900],
            ['sku' => 'LINEN-120', 'category_code' => 'FURNITURE', 'name' => '120" round linen tablecloth', 'unit_label' => 'each', 'unit_price_cents' => 1200],

            // Booth equipment
            ['sku' => 'BOOTH-10X10', 'category_code' => 'BOOTH', 'name' => '10x10 pipe-and-drape booth', 'unit_label' => 'each', 'unit_price_cents' => 15_000],
            ['sku' => 'BOOTH-10X20', 'category_code' => 'BOOTH', 'name' => '10x20 pipe-and-drape booth', 'unit_label' => 'each', 'unit_price_cents' => 28_000],
            ['sku' => 'BOOTH-CARPET', 'category_code' => 'BOOTH', 'name' => 'Booth carpet 10x10', 'unit_label' => 'each', 'unit_price_cents' => 8_000],
            ['sku' => 'BOOTH-SIGN', 'category_code' => 'BOOTH', 'name' => 'Standard booth ID sign', 'unit_label' => 'each', 'unit_price_cents' => 4_500],

            // A/V
            ['sku' => 'AV-PROJECTOR', 'category_code' => 'AV', 'name' => 'HD projector + screen', 'unit_label' => 'day', 'unit_price_cents' => 18_000],
            ['sku' => 'AV-MIC-HAND', 'category_code' => 'AV', 'name' => 'Handheld wireless mic', 'unit_label' => 'day', 'unit_price_cents' => 5_000],
            ['sku' => 'AV-MIC-LAV', 'category_code' => 'AV', 'name' => 'Lavalier wireless mic', 'unit_label' => 'day', 'unit_price_cents' => 6_500],
            ['sku' => 'AV-SPEAKER', 'category_code' => 'AV', 'name' => 'Powered PA speaker', 'unit_label' => 'day', 'unit_price_cents' => 7_500],
            ['sku' => 'AV-MONITOR-55', 'category_code' => 'AV', 'name' => '55" LED monitor + stand', 'unit_label' => 'day', 'unit_price_cents' => 22_000],

            // Electrical
            ['sku' => 'ELEC-5A', 'category_code' => 'ELECTRICAL', 'name' => '500W (5A) booth power', 'unit_label' => 'each', 'unit_price_cents' => 6_000],
            ['sku' => 'ELEC-10A', 'category_code' => 'ELECTRICAL', 'name' => '1000W (10A) booth power', 'unit_label' => 'each', 'unit_price_cents' => 9_500],
            ['sku' => 'ELEC-20A', 'category_code' => 'ELECTRICAL', 'name' => '2000W (20A) booth power', 'unit_label' => 'each', 'unit_price_cents' => 16_000],
            ['sku' => 'ELEC-CORD', 'category_code' => 'ELECTRICAL', 'name' => 'Extension cord 25ft + power strip', 'unit_label' => 'each', 'unit_price_cents' => 1_800],

            // Catering
            ['sku' => 'CAT-COFFEE', 'category_code' => 'CATERING', 'name' => 'Coffee service (50 cups)', 'unit_label' => 'each', 'unit_price_cents' => 12_500],
            ['sku' => 'CAT-WATER', 'category_code' => 'CATERING', 'name' => 'Bottled water case (24)', 'unit_label' => 'case', 'unit_price_cents' => 4_500],
            ['sku' => 'CAT-LUNCH', 'category_code' => 'CATERING', 'name' => 'Boxed lunch (per person)', 'unit_label' => 'each', 'unit_price_cents' => 2_400],
            ['sku' => 'CAT-RECEPTION', 'category_code' => 'CATERING', 'name' => 'Hors d\'oeuvres reception (per person)', 'unit_label' => 'each', 'unit_price_cents' => 3_800],

            // Labor (no tax)
            ['sku' => 'LABOR-SETUP', 'category_code' => 'LABOR', 'name' => 'Setup labor (per hour)', 'unit_label' => 'hour', 'unit_price_cents' => 5_500],
            ['sku' => 'LABOR-TEARDOWN', 'category_code' => 'LABOR', 'name' => 'Teardown labor (per hour)', 'unit_label' => 'hour', 'unit_price_cents' => 5_500],
            ['sku' => 'LABOR-FORKLIFT', 'category_code' => 'LABOR', 'name' => 'Forklift + operator', 'unit_label' => 'hour', 'unit_price_cents' => 12_500],
        ];

        foreach ($items as $row) {
            $cat = EquipmentCategory::query()->where('code', $row['category_code'])->first();
            if ($cat === null) {
                continue;
            }
            EquipmentItem::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'equipment_category_id' => $cat->id,
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'unit_label' => $row['unit_label'],
                    'unit_price_cents' => $row['unit_price_cents'],
                    'is_active' => true,
                ],
            );
        }
    }
}
