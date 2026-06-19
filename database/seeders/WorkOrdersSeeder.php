<?php

namespace Database\Seeders;

use App\Enums\InventoryAction;
use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\ResourceInventory;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderTemplate;
use App\Services\RecurringTemplateMaterializer;
use Illuminate\Database\Seeder;

/**
 * Seeds 3 recurring templates per active venue (weekly HVAC, monthly fire-
 * extinguisher inspection, quarterly deep clean), 14 days of materialized
 * work orders from them, plus a spread of one-off orders across statuses.
 * Idempotent: skips if any work_order_templates exist.
 */
class WorkOrdersSeeder extends Seeder
{
    public function run(): void
    {
        if (WorkOrderTemplate::query()->count() > 0) {
            $this->command?->info('WorkOrdersSeeder: templates already exist, skipping.');

            return;
        }

        $venues = Venue::query()->active()->get();
        if ($venues->isEmpty()) {
            $this->command?->warn('WorkOrdersSeeder: no active venues. Run the venue seeders first.');

            return;
        }

        $templates = [
            [
                'name' => 'Weekly HVAC filter check',
                'kind' => WorkOrderKind::PreventiveMaintenance,
                'recurrence_rrule' => 'FREQ=WEEKLY;BYDAY=MO;BYHOUR=8;BYMINUTE=0',
                'items_json' => [
                    ['sku' => 'FLT-20x20', 'name' => 'HVAC filter 20x20', 'quantity' => 2, 'unit' => 'each', 'unit_cost_cents' => 1_250, 'action' => InventoryAction::Consume->value],
                ],
                'default_assignee_role' => 'ops_lead',
            ],
            [
                'name' => 'Monthly fire-extinguisher inspection',
                'kind' => WorkOrderKind::PreventiveMaintenance,
                'recurrence_rrule' => 'FREQ=MONTHLY;BYMONTHDAY=1;BYHOUR=9;BYMINUTE=0',
                'items_json' => null,
                'default_assignee_role' => 'ops_lead',
            ],
            [
                'name' => 'Quarterly deep clean',
                'kind' => WorkOrderKind::Cleaning,
                'recurrence_rrule' => 'FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=15',
                'items_json' => [
                    ['sku' => 'CLN-KIT', 'name' => 'Cleaning supplies', 'quantity' => 1, 'unit' => 'kit', 'unit_cost_cents' => 4_500, 'action' => InventoryAction::Consume->value],
                ],
                'default_assignee_role' => 'ops_lead',
            ],
        ];

        foreach ($venues as $venue) {
            foreach ($templates as $tpl) {
                WorkOrderTemplate::query()->create(array_merge($tpl, [
                    'venue_id' => $venue->id,
                    'kind' => $tpl['kind']->value,
                    'lookahead_days' => 14,
                    'is_active' => true,
                ]));
            }
        }

        // materialize the next 14 days now; daily cron carries the cadence
        $count = app(RecurringTemplateMaterializer::class)->materializeAll();
        $this->command?->info("WorkOrdersSeeder: materialized {$count} recurring work orders.");

        // spread of one-off orders across statuses/kinds/days; dayOffset
        // pins items to intentional days instead of all random
        $statusBuckets = [
            [WorkOrderStatus::Open, WorkOrderKind::Repair, 'Loose ceiling tile near west exit', 2],
            [WorkOrderStatus::Open, WorkOrderKind::Repair, 'Buzzing fluorescent in pre-function corridor', 4],
            [WorkOrderStatus::Open, WorkOrderKind::InventoryReplenishment, 'Reorder folding chairs (low stock)', 6],
            [WorkOrderStatus::Open, WorkOrderKind::InventoryReplenishment, 'Reorder linens - ivory + navy', 8],
            [WorkOrderStatus::Open, WorkOrderKind::Repair, 'Replace bathroom faucet (drip)', 1],
            [WorkOrderStatus::Assigned, WorkOrderKind::Setup, 'Set 60 rounds of 10 + head table for gala', 5],
            [WorkOrderStatus::Assigned, WorkOrderKind::Setup, 'Build classroom-style for Bar CLE', 13],
            [WorkOrderStatus::Assigned, WorkOrderKind::Cleaning, 'Pressure-wash main entrance pavers', 3],
            [WorkOrderStatus::Assigned, WorkOrderKind::PreventiveMaintenance, 'Inspect kitchen grease trap', 9],
            [WorkOrderStatus::InProgress, WorkOrderKind::Repair, 'Repaint scuffed ballroom corner', 0],
            [WorkOrderStatus::InProgress, WorkOrderKind::Setup, 'Stage build for symphony concert', 14],
            [WorkOrderStatus::InProgress, WorkOrderKind::Repair, 'Roof flashing patch - Cabin 2', 1],
            [WorkOrderStatus::Completed, WorkOrderKind::Teardown, 'Teardown after Bridal Affair Spring Showcase', -1],
            [WorkOrderStatus::Completed, WorkOrderKind::Cleaning, 'Carpet extraction in main ballroom', -3],
            [WorkOrderStatus::Completed, WorkOrderKind::Setup, 'Sound check setup - Air Station Family Day', -5],
            [WorkOrderStatus::Completed, WorkOrderKind::Repair, 'Replace burnt-out arena floor light', -7],
            [WorkOrderStatus::Completed, WorkOrderKind::Cleaning, 'Detail clean - Bayfront Terrace post-event', -2],
            [WorkOrderStatus::Open, WorkOrderKind::Setup, 'Stage prep for Hospitality Showcase load-in', 19],
            [WorkOrderStatus::Open, WorkOrderKind::PreventiveMaintenance, 'HVAC pre-summer compressor check', 11],
        ];

        foreach ($statusBuckets as [$status, $kind, $title, $dayOffset]) {
            $venue = $venues->random();
            $scheduledFor = now()->addDays($dayOffset)->setHour(random_int(7, 16))->setMinute(0);
            $wo = WorkOrder::query()->create([
                'venue_id' => $venue->id,
                'title' => $title,
                'kind' => $kind->value,
                'status' => $status->value,
                'priority' => random_int(1, 4),
                'scheduled_for' => $scheduledFor,
                'cost_cents' => random_int(0, 50_000),
                'completed_at' => $status === WorkOrderStatus::Completed ? $scheduledFor : null,
            ]);

            // completed orders link to a real resource and mark applied so
            // the inventory use-activity report has signal out of the box
            $resource = $status === WorkOrderStatus::Completed
                ? ResourceInventory::query()->where('venue_id', $venue->id)->inRandomOrder()->first()
                : null;

            WorkOrderItem::query()->create([
                'work_order_id' => $wo->id,
                'resource_inventory_id' => $resource?->id,
                'sku' => $resource?->sku ?? strtoupper(substr(preg_replace('/[^a-z]/i', '', $title) ?? 'WO', 0, 3)).'-001',
                'name' => $resource?->name ?? 'Materials',
                'quantity' => random_int(1, 10),
                'unit' => 'each',
                'unit_cost_cents' => random_int(500, 8_000),
                'action' => InventoryAction::Consume->value,
                'applied_at' => $status === WorkOrderStatus::Completed ? $scheduledFor : null,
            ]);
        }

        $this->command?->info('WorkOrdersSeeder: created '.count($statusBuckets).' ad-hoc work orders.');
    }
}
