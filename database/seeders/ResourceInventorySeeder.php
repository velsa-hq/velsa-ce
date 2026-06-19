<?php

namespace Database\Seeders;

use App\Models\ResourceInventory;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Baseline equipment catalog per active venue. Resources are
 * venue-scoped (unique on [venue_id, sku]); quantity_available starts
 * below quantity_total to simulate deployment. Idempotent.
 */
class ResourceInventorySeeder extends Seeder
{
    /**
     * Durable assets carry no reorder point; consumables do. Two
     * consumables seed below reorder so the replenishment report has signal.
     *
     * @var list<array{kind:string,sku:string,name:string,total:int,out:int,reorder:int,consumable:bool}>
     */
    protected array $catalog = [
        // durable assets, no reorder
        ['kind' => 'chairs', 'sku' => 'CHR-STD', 'name' => 'Stacking chairs', 'total' => 500, 'out' => 60, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'tables', 'sku' => 'TBL-RND60', 'name' => '60" round tables', 'total' => 80, 'out' => 12, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'tables', 'sku' => 'TBL-BNQ8', 'name' => '8ft banquet tables', 'total' => 120, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'staging', 'sku' => 'STG-48', 'name' => 'Stage decks 4x8', 'total' => 24, 'out' => 4, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'av', 'sku' => 'AV-MIC', 'name' => 'Wireless mic kits', 'total' => 16, 'out' => 2, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'av', 'sku' => 'AV-PROJ', 'name' => 'Projector + screen kits', 'total' => 8, 'out' => 1, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'tents', 'sku' => 'TNT-1010', 'name' => '10x10 pop-up tents', 'total' => 12, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'power', 'sku' => 'GEN-5K', 'name' => '5kW generators', 'total' => 4, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'barricade', 'sku' => 'FEN-BAR', 'name' => 'Crowd barricade sections', 'total' => 60, 'out' => 10, 'reorder' => 0, 'consumable' => false],
        // consumables, reorder tracked
        ['kind' => 'linens', 'sku' => 'LIN-WHT', 'name' => 'Table linens - white', 'total' => 300, 'out' => 40, 'reorder' => 60, 'consumable' => true],
        ['kind' => 'supplies', 'sku' => 'CLN-KIT', 'name' => 'Cleaning supply kits', 'total' => 40, 'out' => 32, 'reorder' => 15, 'consumable' => true],
        ['kind' => 'supplies', 'sku' => 'BAT-AAA', 'name' => 'AAA batteries (bulk)', 'total' => 200, 'out' => 40, 'reorder' => 50, 'consumable' => true],
        ['kind' => 'supplies', 'sku' => 'TAPE-GAFF', 'name' => 'Gaffer tape (rolls)', 'total' => 24, 'out' => 20, 'reorder' => 12, 'consumable' => true],

        // SKUs match the EquipmentItem catalog so a generated work order
        // links to venue stock and completing it draws the quantity down
        ['kind' => 'chairs', 'sku' => 'CHAIR-FOLD', 'name' => 'Folding chair', 'total' => 600, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'tables', 'sku' => 'TABLE-6FT', 'name' => '6ft rectangular table', 'total' => 150, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'tables', 'sku' => 'TABLE-ROUND-60', 'name' => '60" round table', 'total' => 90, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'booth', 'sku' => 'BOOTH-10X10', 'name' => '10x10 pipe-and-drape booth', 'total' => 60, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'booth', 'sku' => 'BOOTH-CARPET', 'name' => 'Booth carpet 10x10', 'total' => 80, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'av', 'sku' => 'AV-PROJECTOR', 'name' => 'HD projector + screen', 'total' => 20, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'av', 'sku' => 'AV-SPEAKER', 'name' => 'Powered PA speaker', 'total' => 24, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'power', 'sku' => 'ELEC-20A', 'name' => '2000W (20A) booth power', 'total' => 40, 'out' => 0, 'reorder' => 0, 'consumable' => false],
        ['kind' => 'power', 'sku' => 'ELEC-10A', 'name' => '1000W (10A) booth power', 'total' => 50, 'out' => 0, 'reorder' => 0, 'consumable' => false],
    ];

    public function run(): void
    {
        if (ResourceInventory::query()->exists()) {
            $this->command?->info('ResourceInventorySeeder: inventory already present, skipping.');

            return;
        }

        $venues = Venue::query()->whereNotNull('active_at')->get();
        if ($venues->isEmpty()) {
            $this->command?->warn('ResourceInventorySeeder: no active venues. Seed venues first.');

            return;
        }

        $count = 0;
        foreach ($venues as $venue) {
            foreach ($this->catalog as $row) {
                ResourceInventory::query()->create([
                    'venue_id' => $venue->id,
                    'kind' => $row['kind'],
                    'sku' => $row['sku'],
                    'name' => $row['name'],
                    'quantity_total' => $row['total'],
                    'quantity_available' => $row['total'] - $row['out'],
                    'reorder_point' => $row['reorder'],
                    'is_consumable' => $row['consumable'],
                ]);
                $count++;
            }
        }

        $this->command?->info("ResourceInventorySeeder: seeded {$count} resources across {$venues->count()} venues.");
    }
}
