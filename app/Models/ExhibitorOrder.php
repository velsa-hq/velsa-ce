<?php

namespace App\Models;

use App\Enums\ExhibitorOrderStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ExhibitorOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property ExhibitorOrderStatus $status
 * @property Carbon|null $placed_at
 */
class ExhibitorOrder extends Model
{
    /** @use HasFactory<ExhibitorOrderFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'exhibitor_id',
        'order_number',
        'status',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'paid_cents',
        'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExhibitorOrderStatus::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ExhibitorOrder $order): void {
            if (empty($order->order_number)) {
                $year = date('Y');
                do {
                    $suffix = strtoupper(Str::random(6));
                    $candidate = "EX-{$year}-{$suffix}";
                } while (static::query()->where('order_number', $candidate)->exists());
                $order->order_number = $candidate;
            }
        });

        // deleting an order restores inventory its work orders applied, then removes them
        static::deleting(function (ExhibitorOrder $order): void {
            foreach ($order->workOrders as $workOrder) {
                $workOrder->reverseInventoryDeltas();
                $workOrder->delete();
            }
        });
    }

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExhibitorOrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExhibitorPayment::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** Placed and out of the cart state. */
    public function isConfirmed(): bool
    {
        return $this->placed_at !== null
            && $this->status !== ExhibitorOrderStatus::Cart;
    }

    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('line_total_cents');
        $tax = (int) round($subtotal * 0.07); // Florida sales tax stub
        $this->update([
            'subtotal_cents' => $subtotal,
            'tax_cents' => $tax,
            'total_cents' => $subtotal + $tax,
        ]);
    }

    public function applyPayment(int $amountCents): void
    {
        $this->paid_cents += $amountCents;
        if ($this->paid_cents >= $this->total_cents) {
            $this->status = ExhibitorOrderStatus::Paid->value;
        } elseif ($this->paid_cents > 0) {
            $this->status = ExhibitorOrderStatus::PartiallyPaid->value;
        }
        $this->save();
    }

    public function reversePayment(int $amountCents): void
    {
        $this->paid_cents = max(0, $this->paid_cents - $amountCents);
        if ($this->paid_cents === 0) {
            $this->status = ExhibitorOrderStatus::Pending->value;
        } elseif ($this->paid_cents < $this->total_cents) {
            $this->status = ExhibitorOrderStatus::PartiallyPaid->value;
        }
        $this->save();
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }
}
