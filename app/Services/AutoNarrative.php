<?php

namespace App\Services;

use App\Enums\BookingNarrativeKind;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Append synthesized "System" narrative entries to a Booking from model
 * observers on lifecycle events, so manual + auto entries share one
 * chronological log. Attributed to the current User when one exists;
 * otherwise unattributed (e.g. nightly dunning job).
 */
class AutoNarrative
{
    public function append(Booking $booking, string $body): void
    {
        // stamp a user id only for staff Users, not exhibitors on the portal guard
        $user = Auth::user();
        $userId = $user instanceof User
            ? (int) $user->getAuthIdentifier()
            : null;

        $booking->narratives()->create([
            'author_user_id' => $userId,
            'kind' => BookingNarrativeKind::System->value,
            'body' => $body,
            'happened_at' => now(),
        ]);
    }
}
