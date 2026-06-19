<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\AreaUnit;
use Illuminate\Support\Carbon;

/**
 * Spaces with no blocking booking overlapping the requested window.
 */
class LocationAvailabilityReport implements ReportHandler
{
    public function slug(): string
    {
        return 'location-availability';
    }

    public function category(): string
    {
        return 'Scheduling';
    }

    public function title(): string
    {
        return 'Location availability';
    }

    public function description(): string
    {
        return 'Spaces with no Definite or Completed booking overlapping the requested window. Tentatives and holds do not block availability.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(7)->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from']) : now();
        $to = isset($params['to']) ? Carbon::parse($params['to']) : now()->addDays(7);
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $blockingStatuses = [BookingStatus::Definite->value, BookingStatus::Completed->value];

        $busySpaceIds = BookingSpace::query()
            ->where('start_at', '<', $to)
            ->where('end_at', '>', $from)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', $blockingStatuses))
            ->distinct()
            ->pluck('space_id')
            ->all();

        $spaces = Space::query()
            ->with('venue:id,name,slug')
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->whereNotIn('id', $busySpaceIds)
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get();

        $rows = $spaces->map(fn (Space $s) => [
            'venue' => $s->venue?->name ?? '-',
            'name' => $s->name,
            'kind' => $s->kind ?? '-',
            'capacity' => $s->capacity ?? '-',
            'sqft' => $s->sqft !== null ? AreaUnit::fromSqft($s->sqft) : '-',
        ])->all();

        $byVenue = [];
        foreach ($spaces as $s) {
            $key = $s->venue?->name ?? '-';
            $byVenue[$key] = ($byVenue[$key] ?? 0) + 1;
        }

        $summary = [
            ['label' => 'Available spaces', 'value' => (string) $spaces->count()],
            ['label' => 'Venues with openings', 'value' => (string) count($byVenue)],
        ];
        foreach ($byVenue as $venueName => $count) {
            $summary[] = ['label' => $venueName, 'value' => (string) $count];
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('Open %s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'name', 'label' => 'Space'],
                ['key' => 'kind', 'label' => 'Kind'],
                ['key' => 'capacity', 'label' => 'Capacity', 'align' => 'right'],
                ['key' => 'sqft', 'label' => 'Area ('.AreaUnit::label().')', 'align' => 'right'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function venueOptions(): array
    {
        return Venue::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['value' => (int) $v->id, 'label' => $v->name])
            ->all();
    }
}
