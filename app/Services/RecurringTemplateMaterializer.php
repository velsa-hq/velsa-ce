<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderTemplate;
use App\Services\WorkOrders\WorkOrderAssigneeResolver;
use DateTime;
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\Constraint\BetweenConstraint;

/**
 * Materializes recurring WorkOrderTemplates into WorkOrder rows for the
 * upcoming window. Idempotent: keyed on template_id + scheduled_for at
 * minute precision, so re-running won't duplicate. Runs daily from a
 * scheduled command.
 */
class RecurringTemplateMaterializer
{
    /**
     * Materialize all active recurring templates; returns work orders created.
     */
    public function materializeAll(): int
    {
        $created = 0;
        foreach (WorkOrderTemplate::query()->activeRecurring()->get() as $template) {
            $created += $this->materializeTemplate($template);
        }

        return $created;
    }

    public function materializeTemplate(WorkOrderTemplate $template): int
    {
        if ($template->recurrence_rrule === null) {
            return 0;
        }

        // anchor at start-of-day so repeated calls in the same UTC day yield
        // identical occurrence timestamps - otherwise a later now() shifts
        // every DAILY occurrence and the idempotency check below misses
        $start = now()->startOfDay();
        $end = $start->copy()->addDays($template->lookahead_days);

        $rule = new Rule(
            $template->recurrence_rrule,
            new DateTime($start->toDateTimeString()),
        );

        $transformer = new ArrayTransformer;
        $constraint = new BetweenConstraint(
            new DateTime($start->toDateTimeString()),
            new DateTime($end->toDateTimeString()),
            true,
        );

        $created = 0;
        foreach ($transformer->transform($rule, $constraint) as $occurrence) {
            $scheduledFor = $occurrence->getStart();

            // skip if already materialized for this template + minute
            $exists = WorkOrder::query()
                ->where('template_id', $template->id)
                ->where('scheduled_for', $scheduledFor)
                ->exists();
            if ($exists) {
                continue;
            }

            $workOrder = WorkOrder::query()->create([
                'venue_id' => $template->venue_id,
                'template_id' => $template->id,
                'title' => $template->name,
                'kind' => $template->kind->value,
                'status' => WorkOrderStatus::Open->value,
                'priority' => 3,
                'scheduled_for' => $scheduledFor,
                // resolve default assignee role, venue-scoped where possible
                'assigned_to_user_id' => app(WorkOrderAssigneeResolver::class)
                    ->resolve($template->default_assignee_role, $template->venue_id),
            ]);

            foreach ($template->items_json ?? [] as $itemRow) {
                WorkOrderItem::query()->create([
                    'work_order_id' => $workOrder->id,
                    'sku' => $itemRow['sku'] ?? null,
                    'name' => $itemRow['name'] ?? 'Item',
                    'quantity' => $itemRow['quantity'] ?? 1,
                    'unit' => $itemRow['unit'] ?? null,
                    'unit_cost_cents' => $itemRow['unit_cost_cents'] ?? null,
                    'action' => $itemRow['action'] ?? 'consume',
                    'resource_inventory_id' => $itemRow['resource_inventory_id'] ?? null,
                ]);
            }

            $created++;
        }

        $template->update(['last_materialized_at' => now()]);

        return $created;
    }
}
