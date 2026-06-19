<?php

namespace App\Models;

use Database\Factories\ExhibitorOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExhibitorOrderItem extends Model
{
    /** @use HasFactory<ExhibitorOrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'exhibitor_order_id',
        'equipment_item_id',
        'sku',
        'name',
        'department',
        'gl_account',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ExhibitorOrderItem $item): void {
            $item->line_total_cents = $item->quantity * $item->unit_price_cents;
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ExhibitorOrder::class, 'exhibitor_order_id');
    }

    public function equipmentItem(): BelongsTo
    {
        return $this->belongsTo(EquipmentItem::class);
    }

    /** Snapshot catalog data onto a line so history survives renames and price changes. */
    public static function fromCatalog(ExhibitorOrder $order, EquipmentItem $item, int $quantity): self
    {
        // catalog price is the advance rate; late-order surcharge applies past the deadline
        $event = ExhibitorEvent::query()
            ->whereHas('exhibitors', fn ($q) => $q->whereKey($order->exhibitor_id))
            ->first();
        $unitPrice = $event?->pricedNowCents($item->unit_price_cents) ?? $item->unit_price_cents;

        return $order->items()->create([
            'equipment_item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->name,
            'department' => $item->category?->department,
            'gl_account' => $item->effectiveCreditAccountCode(),
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
        ]);
    }
}
