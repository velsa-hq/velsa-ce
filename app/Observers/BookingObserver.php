<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\AutoNarrative;

class BookingObserver
{
    public function __construct(protected AutoNarrative $autoNarrative) {}

    /**
     * Append a narrative on status transitions. Creation isn't logged;
     * the audit log already covers record creation.
     */
    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        // getOriginal() applies casts, so status comes back as an enum (or
        // null); normalize both sides to the raw string
        $originalRaw = $booking->getOriginal('status');
        $original = $originalRaw instanceof \BackedEnum ? $originalRaw->value : $originalRaw;
        $current = $booking->status?->value;

        if ($original === null || $current === null || $original === $current) {
            return;
        }

        $this->autoNarrative->append(
            $booking,
            "Status changed from {$original} to {$current}.",
        );
    }
}
