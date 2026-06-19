<?php

namespace App\Models;

use Database\Factories\StaffAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shift-level staff roster on a Booking: one user working one role from
 * start_at -> end_at at an hourly rate. Multiple rows per user/booking are
 * allowed (split shifts, multi-day events). The outline editor uses these as
 * the candidate pool for OutlineItem.responsible_user_id.
 */
class StaffAssignment extends Model
{
    /** @use HasFactory<StaffAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'role',
        'start_at',
        'end_at',
        'hourly_rate_cents',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'hourly_rate_cents' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForBooking(Builder $query, int $bookingId): Builder
    {
        return $query->where('booking_id', $bookingId);
    }

    public function durationHours(): float
    {
        return (float) $this->start_at->diffInMinutes($this->end_at) / 60;
    }

    public function laborCostCents(): int
    {
        return (int) round($this->durationHours() * $this->hourly_rate_cents);
    }
}
