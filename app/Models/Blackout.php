<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BlackoutFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An unavailability window on a Venue or Space. The conflict check treats
 * it like a definite booking; overlap is rejected with the reason string.
 */
class Blackout extends Model
{
    /** @use HasFactory<BlackoutFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'blackoutable_type',
        'blackoutable_id',
        'starts_at',
        'ends_at',
        'reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function blackoutable(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Blackouts overlapping the range. Half-open [start, end): touching
     * boundaries don't count, matching BookingSpace's check.
     */
    public function scopeOverlappingWindow(
        Builder $query,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): Builder {
        return $query
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }
}
