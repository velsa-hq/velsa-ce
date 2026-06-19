<?php

namespace App\Models;

use App\Enums\InventoryAction;
use Carbon\CarbonInterface;
use Database\Factories\WorkOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonInterface|null $applied_at
 */
class WorkOrderItem extends Model
{
    /** @use HasFactory<WorkOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'resource_inventory_id',
        'exhibitor_order_item_id',
        'sku',
        'name',
        'quantity',
        'unit',
        'unit_cost_cents',
        'action',
        'applied_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'action' => InventoryAction::class,
            'quantity' => 'integer',
            'unit_cost_cents' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(ResourceInventory::class, 'resource_inventory_id');
    }

    /**
     * Apply the action's delta to the linked ResourceInventory and set
     * applied_at so a re-run is a no-op.
     */
    public function applyToInventory(): void
    {
        if ($this->applied_at !== null) {
            return;
        }

        $resource = $this->resource;
        if ($resource !== null) {
            $deltaAvailable = $this->action->deltaAvailable($this->quantity);
            $deltaTotal = $this->action->deltaTotal($this->quantity);

            $resource->update([
                'quantity_available' => max(0, $resource->quantity_available + $deltaAvailable),
                'quantity_total' => max(0, $resource->quantity_total + $deltaTotal),
            ]);
        }

        $this->update(['applied_at' => now()]);
    }

    /**
     * Undo a previously-applied delta and clear applied_at so a reopened
     * or cancelled work order returns its stock. No-op if unapplied.
     */
    public function reverseFromInventory(): void
    {
        if ($this->applied_at === null) {
            return;
        }

        $resource = $this->resource;
        if ($resource !== null) {
            $deltaAvailable = $this->action->deltaAvailable($this->quantity);
            $deltaTotal = $this->action->deltaTotal($this->quantity);

            $resource->update([
                'quantity_available' => max(0, $resource->quantity_available - $deltaAvailable),
                'quantity_total' => max(0, $resource->quantity_total - $deltaTotal),
            ]);
        }

        $this->update(['applied_at' => null]);
    }
}
