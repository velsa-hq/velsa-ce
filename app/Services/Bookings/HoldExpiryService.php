<?php

namespace App\Services\Bookings;

use App\Enums\BookingStatus;
use App\Enums\HoldRank;
use App\Mail\HoldExpired;
use App\Mail\HoldPromoted;
use App\Models\Booking;
use Illuminate\Support\Facades\Mail;

/**
 * Releases holds past hold_expires_at and promotes the ranked holds queued
 * behind them on the same space + window. A released hold becomes Cancelled,
 * freeing the slot; lower-ranked holds on the shared window shift up one
 * position, and whoever moves into 1st is notified.
 */
class HoldExpiryService
{
    /**
     * @return array{expired: int, promoted: int}
     */
    public function expireDue(): array
    {
        $due = Booking::query()
            ->where('status', BookingStatus::Hold->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->with(['spaces', 'owner', 'venue'])
            ->get();

        $expired = 0;
        $promoted = 0;

        foreach ($due as $hold) {
            // snapshot what it occupied before releasing it
            $occupied = $hold->spaces->map(fn ($s) => [
                'space_id' => $s->space_id,
                'start' => $s->start_at,
                'end' => $s->end_at,
            ])->all();
            $expiredRank = $this->rankPosition($hold->hold_rank);

            $hold->update([
                'status' => BookingStatus::Cancelled->value,
                'cancel_reason' => 'Hold expired',
            ]);
            $expired++;

            if ($hold->owner?->email !== null) {
                Mail::to($hold->owner->email)->queue(new HoldExpired($hold));
            }

            $promoted += $this->promoteBehind($hold, $occupied, $expiredRank);
        }

        return ['expired' => $expired, 'promoted' => $promoted];
    }

    /**
     * Shift holds ranked behind the released one up by a position. Only holds
     * overlapping a freed space+window move; whoever reaches 1st is notified.
     *
     * @param  list<array{space_id:int,start:mixed,end:mixed}>  $occupied
     */
    private function promoteBehind(Booking $expired, array $occupied, int $expiredRank): int
    {
        // unranked hold held no queue position, so nothing shifts
        if ($expiredRank < 1 || $occupied === []) {
            return 0;
        }

        $candidates = Booking::query()
            ->where('status', BookingStatus::Hold->value)
            ->where('id', '!=', $expired->getKey())
            ->whereHas('spaces', function ($q) use ($occupied) {
                $q->where(function ($qq) use ($occupied) {
                    foreach ($occupied as $o) {
                        $qq->orWhere(fn ($w) => $w
                            ->where('space_id', $o['space_id'])
                            ->where('start_at', '<', $o['end'])
                            ->where('end_at', '>', $o['start']));
                    }
                });
            })
            ->with('owner')
            ->get();

        $promoted = 0;

        foreach ($candidates as $candidate) {
            $rank = $this->rankPosition($candidate->hold_rank);

            if ($rank <= $expiredRank) {
                continue; // ahead of or equal to the released hold, unaffected
            }

            $newRank = $this->rankFromPosition($rank - 1);

            if ($newRank === null) {
                continue;
            }

            $candidate->update(['hold_rank' => $newRank]);
            $promoted++;

            if ($newRank === HoldRank::First && $candidate->owner?->email !== null) {
                Mail::to($candidate->owner->email)->queue(new HoldPromoted($candidate));
            }
        }

        return $promoted;
    }

    /** Queue position of a rank (1st = 1); 0 for an unranked hold. */
    private function rankPosition(?HoldRank $rank): int
    {
        return match ($rank) {
            HoldRank::First => 1,
            HoldRank::Second => 2,
            HoldRank::Third => 3,
            default => 0,
        };
    }

    private function rankFromPosition(int $position): ?HoldRank
    {
        return match ($position) {
            1 => HoldRank::First,
            2 => HoldRank::Second,
            3 => HoldRank::Third,
            default => null,
        };
    }
}
