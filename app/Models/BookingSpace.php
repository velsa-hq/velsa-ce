<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingSpaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Places a Booking on a Space for a time window.
 *
 * Overlap protection: a BookingSpace cannot overlap an existing one on the
 * same space whose parent booking is blocking (definite/completed). Holds and
 * tentatives may coexist - overlapping holds at different ranks is intentional.
 *
 * Partitioned spaces use the spaces.parent_space_id hierarchy: each section is
 * its own Space, so the conflict check stays a simple space_id match. Valid
 * partition subsets are enforced in BookingStoreRequest / BookingUpdateRequest.
 *
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property int $setup_minutes_before
 * @property int $teardown_minutes_after
 * @property-read Space|null $space
 * @property-read Booking|null $booking
 */
class BookingSpace extends Model
{
    /** @use HasFactory<BookingSpaceFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'space_id',
        'start_at',
        'end_at',
        'setup_minutes_before',
        'teardown_minutes_after',
        'rate_applied_cents',
        'notes',
        'blocks_overlap',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'setup_minutes_before' => 'integer',
            'teardown_minutes_after' => 'integer',
            'rate_applied_cents' => 'integer',
            'blocks_overlap' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BookingSpace $bookingSpace): void {
            $bookingSpace->assertNoBlackoutOverlap();
            $bookingSpace->assertNoBlockingOverlap();
            $bookingSpace->blocks_overlap = $bookingSpace->resolveBlocksOverlap();
        });
    }

    /** drives the DB overlap-exclusion constraint's partial predicate */
    protected function resolveBlocksOverlap(): bool
    {
        $status = $this->booking?->status?->value ?? $this->booking()->value('status');

        return $status !== null && BookingStatus::from($status)->blocksOverlap();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Window occupied for conflict purposes. When the venue enforces buffers
     * the raw window is expanded by setup/teardown minutes to reserve
     * turnaround; otherwise the raw window is returned unchanged.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function conflictWindow(Space $space): array
    {
        $start = $this->start_at;
        $end = $this->end_at;

        if ($space->venue?->enforcesSetupBuffers()) {
            $start = $start->copy()->subMinutes((int) $this->setup_minutes_before);
            $end = $end->copy()->addMinutes((int) $this->teardown_minutes_after);
        }

        return [$start, $end];
    }

    /**
     * Find a conflicting booking_space on the same space under the caller's
     * status constraint. With buffers enabled the comparison runs in PHP on
     * effective windows after a day-padded raw prefilter, since per-row
     * interval math isn't portable across SQLite/MySQL; otherwise it's the
     * plain indexed raw-window overlap.
     */
    protected function findConflict(\Closure $constrain): ?BookingSpace
    {
        $space = $this->space ?? Space::query()->find($this->space_id);
        $enforce = (bool) $space?->venue?->enforcesSetupBuffers();

        $query = static::query()
            ->where('space_id', $this->space_id)
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->getKey()))
            ->with('booking:id,reference,status');
        $constrain($query);

        if (! $enforce || $space === null) {
            return $query
                ->where('start_at', '<', $this->end_at)
                ->where('end_at', '>', $this->start_at)
                ->first();
        }

        [$effStart, $effEnd] = $this->conflictWindow($space);

        return $query
            ->where('start_at', '<', $effEnd->copy()->addDay())
            ->where('end_at', '>', $effStart->copy()->subDay())
            ->get()
            ->first(function (BookingSpace $other) use ($effStart, $effEnd, $space) {
                [$otherStart, $otherEnd] = $other->conflictWindow($space);

                return $effStart->lt($otherEnd) && $effEnd->gt($otherStart);
            });
    }

    /**
     * Reject a save overlapping a Blackout on the space, any ancestor space,
     * or the parent venue. Blackouts are always blocking.
     */
    protected function assertNoBlackoutOverlap(): void
    {
        $space = $this->space ?? Space::query()->find($this->space_id);
        if ($space === null) {
            return; // factories sometimes save before the FK is reachable
        }

        // blackouts carry no buffer, so only this booking's effective window matters
        [$effStart, $effEnd] = $this->conflictWindow($space);

        $spaceIds = collect([$space->id]);
        $ancestor = $space->parent_space_id;
        while ($ancestor !== null) {
            $spaceIds->push($ancestor);
            $ancestor = Space::query()->where('id', $ancestor)->value('parent_space_id');
        }

        $blackout = Blackout::query()
            ->where(function ($q) use ($spaceIds, $space) {
                $q->where(function ($qq) use ($spaceIds) {
                    $qq->where('blackoutable_type', Space::class)
                        ->whereIn('blackoutable_id', $spaceIds);
                })->orWhere(function ($qq) use ($space) {
                    $qq->where('blackoutable_type', Venue::class)
                        ->where('blackoutable_id', $space->venue_id);
                });
            })
            ->overlappingWindow($effStart, $effEnd)
            ->first();

        if ($blackout !== null) {
            throw new RuntimeException(sprintf(
                'Space is unavailable from %s to %s: %s.',
                $blackout->starts_at?->toIso8601String(),
                $blackout->ends_at?->toIso8601String(),
                $blackout->reason,
            ));
        }
    }

    /** Reject a save overlapping an existing booking_space whose parent booking is blocking. */
    protected function assertNoBlockingOverlap(): void
    {
        $blockingStatuses = [BookingStatus::Definite->value, BookingStatus::Completed->value];

        $conflict = $this->findConflict(
            fn ($q) => $q->whereHas('booking', fn ($b) => $b->whereIn('status', $blockingStatuses)),
        );

        if ($conflict !== null) {
            throw new RuntimeException(sprintf(
                'Space is already booked by %s (%s) from %s to %s.',
                $conflict->booking?->reference ?? '#'.$conflict->booking_id,
                $conflict->booking?->status?->value ?? 'unknown',
                $conflict->start_at?->toIso8601String(),
                $conflict->end_at?->toIso8601String(),
            ));
        }

        // a blocking booking cannot overlap any existing row - holds and
        // tentatives lose their slot when a definite locks the time
        $parentStatus = $this->booking?->status?->value
            ?? $this->booking()->value('status');

        if ($parentStatus !== null && in_array($parentStatus, $blockingStatuses, true)) {
            $conflictWithAny = $this->findConflict(
                fn ($q) => $q->where('booking_id', '!=', $this->booking_id),
            );

            if ($conflictWithAny !== null) {
                throw new RuntimeException(sprintf(
                    'Cannot mark booking definite - space is held by booking #%d in the same window.',
                    $conflictWithAny->booking_id,
                ));
            }
        }
    }
}
