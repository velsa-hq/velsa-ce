<?php

namespace App\Services\Operations;

use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Venue;
use Carbon\CarbonImmutable;

/**
 * Builds the FullCalendar events for /ops/calendar: one entry per booking
 * and per blackout. Venue / month / week view (cf. OpsScheduleBuilder's
 * per-space day grid).
 */
class OpsCalendarBuilder
{
    protected const STATUS_COLORS = [
        BookingStatus::Hold->value => ['#f59e0b', 'status-hold'],          // amber-500
        BookingStatus::Tentative->value => ['#0ea5e9', 'status-tentative'], // sky-500
        BookingStatus::Inquiry->value => ['#94a3b8', 'status-inquiry'],    // slate-400
        BookingStatus::Definite->value => ['#10b981', 'status-definite'],  // emerald-500
        BookingStatus::Completed->value => ['#8b5cf6', 'status-completed'], // violet-500
        BookingStatus::Cancelled->value => ['#9ca3af', 'status-cancelled'], // gray-400
    ];

    /**
     * @return array{
     *   events: list<array<string,mixed>>,
     *   venues: list<array{id:int,name:string,slug:string}>
     * }
     */
    public function build(
        CarbonImmutable $start,
        CarbonImmutable $end,
        ?int $venueId = null,
    ): array {
        $bookings = Booking::query()
            ->with(['venue:id,name,slug', 'client:id,name'])
            ->whereBetween('start_at', [$start, $end])
            ->whereNotIn('status', [
                BookingStatus::Cancelled->value,
                BookingStatus::Inquiry->value,
            ])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->orderBy('start_at')
            ->get();

        $events = $bookings->map(function (Booking $b) {
            [$color, $cssClass] = self::STATUS_COLORS[$b->status?->value]
                ?? ['#6b7280', 'status-other'];

            return [
                'id' => "booking-{$b->id}",
                'title' => $b->name ?? $b->reference,
                'start' => $b->start_at?->toIso8601String(),
                'end' => $b->end_at?->toIso8601String(),
                'url' => "/bookings/{$b->id}",
                'backgroundColor' => $color,
                'borderColor' => $color,
                'classNames' => [$cssClass],
                'extendedProps' => [
                    'kind' => 'booking',
                    'reference' => $b->reference,
                    'venue' => $b->venue?->name,
                    'client' => $b->client?->name,
                    'status' => $b->status?->value,
                ],
            ];
        })->all();

        $blackouts = Blackout::query()
            ->with(['blackoutable'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($venueId, function ($q, $v) {
                $q->where(function ($qq) use ($v) {
                    $spaceIds = Space::query()
                        ->where('venue_id', $v)
                        ->pluck('id');
                    $qq->where(function ($q3) use ($v) {
                        $q3->where('blackoutable_type', Venue::class)
                            ->where('blackoutable_id', $v);
                    })->orWhere(function ($q3) use ($spaceIds) {
                        $q3->where('blackoutable_type', Space::class)
                            ->whereIn('blackoutable_id', $spaceIds);
                    });
                });
            })
            ->orderBy('starts_at')
            ->get();

        foreach ($blackouts as $bl) {
            $scope = $bl->blackoutable_type === Venue::class ? 'venue' : 'space';
            $events[] = [
                'id' => "blackout-{$bl->id}",
                // background overlay across day cells, not an event pill
                'display' => 'background',
                'title' => "⛔ {$bl->reason}",
                'start' => $bl->starts_at?->toIso8601String(),
                'end' => $bl->ends_at?->toIso8601String(),
                'backgroundColor' => 'rgba(244, 63, 94, 0.18)', // rose-500 @ 18%
                'classNames' => ['blackout-overlay'],
                'extendedProps' => [
                    'kind' => 'blackout',
                    'reason' => $bl->reason,
                    'scope' => $scope,
                ],
            ];
        }

        $venues = Venue::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Venue $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'slug' => $v->slug,
            ])
            ->all();

        return [
            'events' => $events,
            'venues' => $venues,
        ];
    }
}
