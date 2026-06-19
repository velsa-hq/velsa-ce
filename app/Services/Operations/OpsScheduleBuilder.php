<?php

namespace App\Services\Operations;

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the 14-day per-space schedule grid: spaces as rows, days as columns,
 * bookings + blackouts as bars spanning occupied cells. Location-sliced view
 * (cf. the department-sliced OpsBoard).
 */
class OpsScheduleBuilder
{
    public const WINDOW_DAYS = 14;

    /**
     * @return array{days: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public function build(
        CarbonImmutable $weekStart,
        ?int $venueId = null,
    ): array {
        $start = $weekStart->startOfDay();
        $end = $start->addDays(self::WINDOW_DAYS); // exclusive

        $days = $this->buildDayAxis($start);

        $spaces = $this->fetchSpaces($venueId);
        if ($spaces->isEmpty()) {
            return ['days' => $days, 'rows' => []];
        }

        $spaceIds = $spaces->pluck('id')->all();
        $venueIds = $spaces->pluck('venue_id')->unique()->values()->all();

        $bookingSpaces = $this->fetchBookings($spaceIds, $start, $end);
        $blackouts = $this->fetchBlackouts($spaceIds, $venueIds, $start, $end);

        $rows = $spaces->map(
            fn (Space $space) => $this->buildRow($space, $bookingSpaces, $blackouts, $start)
        )->values()->all();

        return [
            'days' => $days,
            'rows' => $rows,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildDayAxis(CarbonImmutable $start): array
    {
        $today = now()->startOfDay();

        return collect(range(0, self::WINDOW_DAYS - 1))
            ->map(function (int $i) use ($start, $today) {
                $day = $start->addDays($i);

                return [
                    'iso' => $day->toDateString(),
                    'label_top' => $day->format('D'),
                    'label_bottom' => $day->format('M j'),
                    'is_weekend' => $day->isWeekend(),
                    'is_today' => $day->isSameDay($today),
                ];
            })
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Space>
     */
    protected function fetchSpaces(?int $venueId): \Illuminate\Database\Eloquent\Collection
    {
        return Space::query()
            ->with('venue:id,name,slug')
            ->whereNull('retired_at')
            ->when($venueId, fn ($q) => $q->where('venue_id', $venueId))
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<int>  $spaceIds
     * @return Collection<int, BookingSpace>
     */
    protected function fetchBookings(
        array $spaceIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        $excluded = [
            BookingStatus::Cancelled->value,
            BookingStatus::Inquiry->value,
        ];

        return BookingSpace::query()
            ->with([
                'booking:id,reference,name,status,client_id',
                'booking.client:id,name',
            ])
            ->whereIn('space_id', $spaceIds)
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->whereHas('booking', fn ($q) => $q->whereNotIn('status', $excluded))
            ->orderBy('start_at')
            ->get();
    }

    /**
     * @param  array<int>  $spaceIds
     * @param  array<int>  $venueIds
     * @return Collection<int, Blackout>
     */
    protected function fetchBlackouts(
        array $spaceIds,
        array $venueIds,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Collection {
        return Blackout::query()
            ->where(function ($q) use ($spaceIds, $venueIds) {
                $q->where(function ($qq) use ($spaceIds) {
                    $qq->where('blackoutable_type', Space::class)
                        ->whereIn('blackoutable_id', $spaceIds);
                })->orWhere(function ($qq) use ($venueIds) {
                    $qq->where('blackoutable_type', Venue::class)
                        ->whereIn('blackoutable_id', $venueIds);
                });
            })
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get();
    }

    /**
     * @param  Collection<int, BookingSpace>  $bookingSpaces
     * @param  Collection<int, Blackout>  $blackouts
     * @return array<string, mixed>
     */
    protected function buildRow(
        Space $space,
        Collection $bookingSpaces,
        Collection $blackouts,
        CarbonImmutable $windowStart,
    ): array {
        $spaceBookings = $bookingSpaces
            ->where('space_id', $space->id)
            ->map(fn (BookingSpace $bs) => [
                'id' => $bs->booking_id,
                'reference' => $bs->booking?->reference,
                'name' => $bs->booking?->name,
                'status' => $bs->booking?->status?->value,
                'client_name' => $bs->booking?->client?->name,
                'start_idx' => $this->dayIndex($bs->start_at, $windowStart),
                'end_idx' => $this->dayIndex($bs->end_at, $windowStart, isEnd: true),
                'url' => "/bookings/{$bs->booking_id}",
            ])
            ->values()
            ->all();

        $spaceBlackouts = $blackouts
            ->filter(function (Blackout $b) use ($space) {
                if ($b->blackoutable_type === Space::class && $b->blackoutable_id === $space->id) {
                    return true;
                }

                return $b->blackoutable_type === Venue::class
                    && $b->blackoutable_id === $space->venue_id;
            })
            ->map(fn (Blackout $b) => [
                'id' => $b->id,
                'reason' => $b->reason,
                'scope' => $b->blackoutable_type === Venue::class ? 'venue' : 'space',
                'start_idx' => $this->dayIndex($b->starts_at, $windowStart),
                'end_idx' => $this->dayIndex($b->ends_at, $windowStart, isEnd: true),
            ])
            ->values()
            ->all();

        return [
            'id' => $space->id,
            'name' => $space->name,
            'kind' => $space->kind,
            'capacity' => (int) $space->capacity,
            'venue' => $space->venue ? [
                'id' => $space->venue->id,
                'name' => $space->venue->name,
                'slug' => $space->venue->slug,
            ] : null,
            'bookings' => $spaceBookings,
            'blackouts' => $spaceBlackouts,
        ];
    }

    // 0-13 day index within the window; an exact 00:00 end belongs to the prior day
    protected function dayIndex(
        ?\DateTimeInterface $when,
        CarbonImmutable $windowStart,
        bool $isEnd = false,
    ): int {
        if ($when === null) {
            return 0;
        }

        $point = CarbonImmutable::instance($when);
        if ($isEnd && $point->format('H:i:s') === '00:00:00') {
            $point = $point->subSecond();
        }

        // timestamp math sidesteps Carbon diffInDays() sign changes across versions
        $delta = $point->startOfDay()->getTimestamp() - $windowStart->getTimestamp();
        $idx = (int) floor($delta / 86400);

        return max(0, min(self::WINDOW_DAYS - 1, $idx));
    }
}
