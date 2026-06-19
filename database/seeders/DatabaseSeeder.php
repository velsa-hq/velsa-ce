<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // org-agnostic foundations first, then the Sentinel Bay demo
        // dataset, then post-processing over whatever was just seeded
        $this->call([
            RolesAndPermissionsSeeder::class,
            SpaceKindSeeder::class,
            DepartmentSeeder::class,
            EventKindSeeder::class,
            InventoryKindSeeder::class,
            OutlineItemTemplateSeeder::class,

            SentinelBayVenuesSeeder::class,
            SentinelBayBrandingSeeder::class,
            SentinelBayUsersSeeder::class,
            SentinelBaySalesSeeder::class,
            SentinelBayBookingsSeeder::class,
            SentinelBayExhibitorsSeeder::class,

            FundsSeeder::class,
            ChartOfAccountsSeeder::class,
            FiscalYearsSeeder::class,
            EquipmentCatalogSeeder::class,
            ResourceInventorySeeder::class,
            ExportTemplatesSeeder::class,
            ContractsSeeder::class,
            InvoicesBackfillSeeder::class,
            DemoJournalSeeder::class,
            OutlinesSeeder::class,
            WorkOrdersSeeder::class,
            LayoutTemplatesSeeder::class,
            SpaceConstraintsSeeder::class,
            PaymentSchedulesSeeder::class,
            StaffAssignmentsSeeder::class,
        ]);
    }
}
