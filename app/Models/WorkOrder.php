<?php

namespace App\Models;

use App\Enums\WorkOrderKind;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsVenueScoped;
use Carbon\CarbonInterface;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface|null $scheduled_for
 * @property CarbonInterface|null $completed_at
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use Auditable, HasFactory, IsVenueScoped;

    protected $fillable = [
        'venue_id',
        'booking_id',
        'exhibitor_order_id',
        'exhibitor_id',
        'template_id',
        'requested_by_user_id',
        'assigned_to_user_id',
        'reference',
        'title',
        'description',
        'kind',
        'department',
        'status',
        'priority',
        'scheduled_for',
        'completed_at',
        'cost_cents',
    ];

    protected function casts(): array
    {
        return [
            'kind' => WorkOrderKind::class,
            'status' => WorkOrderStatus::class,
            'priority' => 'integer',
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
            'cost_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $wo): void {
            if (empty($wo->reference)) {
                $year = date('Y');
                do {
                    $suffix = strtoupper(Str::random(5));
                    $candidate = "WO-{$year}-{$suffix}";
                } while (static::query()->where('reference', $candidate)->exists());
                $wo->reference = $candidate;
            }
        });

        static::saving(function (WorkOrder $wo): void {
            if ($wo->isDirty('status')) {
                $status = $wo->status instanceof WorkOrderStatus
                    ? $wo->status
                    : WorkOrderStatus::from((string) $wo->status);

                if ($status === WorkOrderStatus::Completed && $wo->completed_at === null) {
                    $wo->completed_at = now();
                }
                if ($status !== WorkOrderStatus::Completed) {
                    $wo->completed_at = null;
                }
            }
        });
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function exhibitorOrder(): BelongsTo
    {
        return $this->belongsTo(ExhibitorOrder::class);
    }

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkOrderTemplate::class, 'template_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function laborLogs(): HasMany
    {
        return $this->hasMany(LaborLog::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->scheduled_for !== null
            && $this->scheduled_for->isPast()
            && $this->status?->isOpen() === true;
    }

    /**
     * Apply all attached items to inventory. Idempotent: already-applied
     * items are skipped.
     */
    public function applyInventoryDeltas(): void
    {
        foreach ($this->items()->whereNull('applied_at')->get() as $item) {
            $item->applyToInventory();
        }
    }

    /**
     * Reverse every applied item's inventory delta (reopen/cancel/delete).
     * Idempotent.
     */
    public function reverseInventoryDeltas(): void
    {
        foreach ($this->items()->whereNotNull('applied_at')->get() as $item) {
            $item->reverseFromInventory();
        }
    }
}
