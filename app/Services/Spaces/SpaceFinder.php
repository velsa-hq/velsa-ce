<?php

namespace App\Services\Spaces;

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Best-fit space finder. Filters by hard constraints (availability,
 * capacity, kind, sqft, venue) then ranks by fit score - smaller
 * spaces that still meet capacity rank first.
 */
class SpaceFinder
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function find(SpaceFinderCriteria $criteria): Collection
    {
        // hard constraints - narrow at the DB level
        $query = Space::query()
            ->with(['venue:id,name,slug,settings_json', 'kindRef:id,key,label'])
            ->whereNull('retired_at');

        if ($criteria->attendance !== null) {
            $query->where('capacity', '>=', $criteria->attendance);
        }
        if ($criteria->minSqft !== null) {
            $query->where('sqft', '>=', $criteria->minSqft);
        }
        if ($criteria->kind !== null) {
            $query->where('kind', $criteria->kind);
        }
        if ($criteria->venueId !== null) {
            $query->where('venue_id', $criteria->venueId);
        }

        $candidates = $query->get();
        if ($candidates->isEmpty()) {
            return collect();
        }

        // in-PHP: the conflict graph (ancestor spaces + venue-wide
        // blackouts) is awkward in pure SQL
        $available = $candidates->filter(
            fn (Space $s) => $this->isAvailable($s, $criteria),
        );

        return $available
            ->map(fn (Space $s) => $this->shape($s, $criteria))
            ->sortByDesc('score')
            ->values();
    }

    protected function isAvailable(Space $space, SpaceFinderCriteria $criteria): bool
    {
        // a blackout or booking on any ancestor space blocks this leaf too
        $spaceIds = [$space->id];
        $ancestor = $space->parent_space_id;
        while ($ancestor !== null) {
            $spaceIds[] = (int) $ancestor;
            $ancestor = Space::query()->where('id', $ancestor)->value('parent_space_id');
        }

        // blackouts on any ancestor space or the parent venue
        $blackoutExists = Blackout::query()
            ->where(function ($q) use ($spaceIds, $space) {
                $q->where(function ($qq) use ($spaceIds) {
                    $qq->where('blackoutable_type', Space::class)
                        ->whereIn('blackoutable_id', $spaceIds);
                })->orWhere(function ($qq) use ($space) {
                    $qq->where('blackoutable_type', Venue::class)
                        ->where('blackoutable_id', $space->venue_id);
                });
            })
            ->where('starts_at', '<', $criteria->endAt)
            ->where('ends_at', '>', $criteria->startAt)
            ->exists();

        if ($blackoutExists) {
            return false;
        }

        // only definite/completed bookings block; holds and tentatives
        // don't own the space yet
        $blocking = BookingSpace::query()
            ->whereIn('space_id', $spaceIds)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
            ]));

        // mirror the save-time conflict rule: a booking's buffered window
        // blocks the requested raw window, so turnaround bleed is excluded
        // here too. day-padded prefilter stays indexable; interval math in
        // PHP for SQLite/MySQL portability
        if (! $space->venue?->enforcesSetupBuffers()) {
            return ! $blocking
                ->where('start_at', '<', $criteria->endAt)
                ->where('end_at', '>', $criteria->startAt)
                ->exists();
        }

        $reqStart = CarbonImmutable::instance($criteria->startAt);
        $reqEnd = CarbonImmutable::instance($criteria->endAt);

        $conflict = $blocking
            ->where('start_at', '<', $reqEnd->addDay())
            ->where('end_at', '>', $reqStart->subDay())
            ->get()
            ->first(function (BookingSpace $bs) use ($reqStart, $reqEnd) {
                $effStart = $bs->start_at->copy()->subMinutes((int) $bs->setup_minutes_before);
                $effEnd = $bs->end_at->copy()->addMinutes((int) $bs->teardown_minutes_after);

                return $effStart->lt($reqEnd) && $effEnd->gt($reqStart);
            });

        return $conflict === null;
    }

    /**
     * Score: 100 for a tight fit, falling as the space exceeds need;
     * kind/venue bonuses adjust within a capacity bracket.
     *
     * @return array<string, mixed>
     */
    protected function shape(Space $space, SpaceFinderCriteria $criteria): array
    {
        $score = 100.0;
        $rationale = [];

        if ($criteria->attendance !== null && $criteria->attendance > 0 && $space->capacity > 0) {
            $ratio = $space->capacity / $criteria->attendance;
            // ratio = 1.0 -> 100, 1.5 -> 67, 2.0 -> 50, 3.0 -> 33, ...
            $score = 100.0 / $ratio;
            $overagePct = ($ratio - 1) * 100;
            if ($overagePct < 25) {
                $rationale[] = 'tight capacity fit';
            } elseif ($overagePct < 75) {
                $rationale[] = sprintf('%d%% over capacity', (int) round($overagePct));
            } else {
                $rationale[] = sprintf('%.1fx larger than needed', $ratio);
            }
        }

        if ($criteria->kind !== null && $space->kind === $criteria->kind) {
            $score += 5;
            $rationale[] = 'exact kind match';
        }

        if ($criteria->venueId !== null && $space->venue_id === $criteria->venueId) {
            $score += 3; // already filtered for; mostly a label
            $rationale[] = 'preferred venue';
        }

        return [
            'id' => $space->id,
            'name' => $space->name,
            'venue' => $space->venue ? [
                'id' => $space->venue->id,
                'name' => $space->venue->name,
                'slug' => $space->venue->slug,
            ] : null,
            'kind' => $space->kind,
            'kind_label' => $space->kindLabel(),
            'capacity' => (int) $space->capacity,
            'sqft' => $space->sqft,
            'bookable_unit' => $space->bookable_unit?->value,
            'score' => round($score, 1),
            'rationale' => implode(' · ', $rationale),
        ];
    }
}
