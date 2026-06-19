<?php

namespace App\Services\Exhibitors;

use App\Enums\ExhibitorOrderStatus;
use App\Enums\InventoryAction;
use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\Booking;
use App\Models\Department;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorOrderItem;
use App\Models\ResourceInventory;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderAssigneeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a confirmed exhibitor order into floor work orders, one per department.
 *
 * Idempotent: reconciles in place on every save rather than spawning
 * duplicates. Completed work orders are left untouched so applied
 * inventory + history stand.
 */
class ExhibitorFulfillmentService
{
    /**
     * @var list<string>
     */
    private array $terminal = [];

    public function __construct(private WorkOrderAssigneeResolver $assignees)
    {
        $this->terminal = [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value];
    }

    public function syncForOrder(ExhibitorOrder $order): void
    {
        $order->loadMissing(['items', 'exhibitor.event.booking']);
        $event = $order->exhibitor?->event;
        $booking = $event?->booking;

        // no event/booking context, or venue opted out of auto-handoff
        if ($event === null || $booking === null || ! $event->generatesWorkOrders()) {
            return;
        }

        if ($order->status === ExhibitorOrderStatus::Cancelled) {
            $this->cancelReconcilable($order);

            return;
        }

        // no longer eligible (refunded, last item removed) - tear down open WOs
        if (! $this->shouldGenerate($order, $event->workOrderTrigger())) {
            $this->cancelReconcilable($order);

            return;
        }

        /** @var Collection<string, Collection<int, ExhibitorOrderItem>> $groups */
        $groups = $order->items->groupBy(fn (ExhibitorOrderItem $i) => $i->department ?: '');

        DB::transaction(function () use ($order, $booking, $groups): void {
            // serialize concurrent observers so check-then-create can't double-generate
            ExhibitorOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            foreach ($groups as $deptKey => $items) {
                $this->upsertDepartmentWorkOrder($order, $booking, (string) $deptKey, $items);
            }

            $liveDepartments = $groups->keys()->map(fn ($k) => (string) $k)->all();

            // departments dropped from the order: cancel their still-open WOs
            $order->workOrders()
                ->whereNotIn('status', $this->terminal)
                ->get()
                ->reject(fn (WorkOrder $wo) => in_array((string) ($wo->department ?? ''), $liveDepartments, true))
                ->each(fn (WorkOrder $wo) => $wo->update(['status' => WorkOrderStatus::Cancelled->value]));
        });
    }

    /**
     * @param  Collection<int, ExhibitorOrderItem>  $items
     */
    private function upsertDepartmentWorkOrder(ExhibitorOrder $order, Booking $booking, string $deptKey, Collection $items): void
    {
        $matchDept = fn ($q) => $deptKey === ''
            ? $q->whereNull('department')
            : $q->where('department', $deptKey);

        // completed WO is historical - inventory already applied, leave alone
        $hasCompleted = $order->workOrders()
            ->where($matchDept)
            ->where('status', WorkOrderStatus::Completed->value)
            ->exists();

        if ($hasCompleted) {
            return;
        }

        $wo = $order->workOrders()
            ->where($matchDept)
            ->whereNotIn('status', $this->terminal)
            ->first();

        $label = $this->departmentLabel($deptKey);
        $booth = $order->exhibitor->booth_assignment;
        $title = trim(($booth ? "Booth {$booth} - " : '').($label !== '' ? "{$label} setup" : 'Booth setup'));

        $attrs = [
            'venue_id' => $booking->venue_id,
            'exhibitor_id' => $order->exhibitor_id,
            'kind' => WorkOrderKind::Setup->value,
            'department' => $deptKey === '' ? null : $deptKey,
            'title' => $title,
            'description' => "Auto-generated from exhibitor order {$order->order_number}.",
            'scheduled_for' => $booking->start_at,
        ];

        if ($wo === null) {
            $wo = $order->workOrders()->create($attrs + [
                'booking_id' => $booking->id,
                'status' => WorkOrderStatus::Open->value,
                'priority' => 3,
                // create-only so a manual reassignment survives later order edits
                'assigned_to_user_id' => $this->assignees->resolve(
                    $this->defaultRoleFor($deptKey),
                    $booking->venue_id,
                ),
            ]);
        } else {
            $wo->update($attrs);
        }

        // match items by source order line so manual tweaks survive an edit
        // (rather than wiping via delete-and-recreate)
        $existing = $wo->items()->whereNotNull('exhibitor_order_item_id')->get()->keyBy('exhibitor_order_item_id');
        $seen = [];

        foreach ($items as $item) {
            $seen[] = $item->id;
            $row = $existing->get($item->id);
            $synced = [
                'resource_inventory_id' => $this->resolveResourceInventoryId($booking->venue_id, $item->sku),
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
            ];

            if ($row !== null) {
                $row->update($synced);
            } else {
                $wo->items()->create($synced + [
                    'exhibitor_order_item_id' => $item->id,
                    'unit_cost_cents' => $item->unit_price_cents,
                    'action' => InventoryAction::Deploy->value,
                ]);
            }
        }

        // drop items whose source order line is gone; leave unlinked items alone
        $wo->items()
            ->whereNotNull('exhibitor_order_item_id')
            ->whereNotIn('exhibitor_order_item_id', $seen)
            ->delete();
    }

    /**
     * A completed setup WO freezes its department: applied inventory + finished
     * work can't be changed by editing the order. Guards order-item edits.
     */
    public function departmentLocked(ExhibitorOrder $order, ?string $department): bool
    {
        return $order->workOrders()
            ->where(fn ($q) => ($department ?? '') === ''
                ? $q->whereNull('department')
                : $q->where('department', $department))
            ->where('status', WorkOrderStatus::Completed->value)
            ->exists();
    }

    private function cancelReconcilable(ExhibitorOrder $order): void
    {
        $order->workOrders()
            ->whereNotIn('status', $this->terminal)
            ->get()
            ->each(fn (WorkOrder $wo) => $wo->update(['status' => WorkOrderStatus::Cancelled->value]));
    }

    private function shouldGenerate(ExhibitorOrder $order, string $trigger): bool
    {
        if ($order->items->isEmpty()) {
            return false;
        }

        if ($trigger === 'paid') {
            return in_array($order->status, [ExhibitorOrderStatus::Paid, ExhibitorOrderStatus::PartiallyPaid], true);
        }

        return $order->isConfirmed();
    }

    /**
     * Match a ResourceInventory row (same SKU + venue) so WO completion can draw
     * stock down. No match -> item is still recorded but moves no stock.
     */
    private function resolveResourceInventoryId(int $venueId, ?string $sku): ?int
    {
        if ($sku === null || $sku === '') {
            return null;
        }

        return ResourceInventory::query()
            ->where('venue_id', $venueId)
            ->where('sku', $sku)
            ->value('id');
    }

    private function departmentLabel(string $deptKey): string
    {
        if ($deptKey === '') {
            return '';
        }

        return Department::query()->where('key', $deptKey)->value('label')
            ?? Str::headline($deptKey);
    }

    private function defaultRoleFor(string $deptKey): ?string
    {
        if ($deptKey === '') {
            return null;
        }

        return Department::query()->where('key', $deptKey)->value('default_role');
    }
}
