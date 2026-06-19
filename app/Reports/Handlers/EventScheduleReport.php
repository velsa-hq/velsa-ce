<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\DateFormatter;
use Illuminate\Support\Carbon;

/**
 * Master chronological schedule of every event in a window. Same date axis as
 * booked-locations but row-per-event (not row-per-space), so it reads as a
 * calendar list rather than a space-utilization grid.
 */
class EventScheduleReport implements ReportHandler
{
    public function slug(): string
    {
        return 'event-schedule';
    }

    public function category(): string
    {
        return 'Scheduling';
    }

    public function title(): string
    {
        return 'Event schedule';
    }

    public function description(): string
    {
        return 'Master chronological list of every booking in the window - event, venue, spaces/locations, client, attendance, and status. One row per event.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(30)->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->addDays(30)->endOfDay();
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $bookings = Booking::query()
            ->with(['venue:id,name', 'client:id,name', 'spaces.space:id,name'])
            ->whereBetween('start_at', [$from, $to])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->orderBy('start_at')
            ->get();

        $rows = $bookings->map(fn (Booking $b) => [
            'date' => $b->start_at?->format('M j, Y'),
            'day' => $b->start_at?->format('D'),
            'time' => DateFormatter::timeOnly($b->start_at),
            'reference' => $b->reference,
            'name' => $b->name,
            'venue' => $b->venue?->name ?? '-',
            'spaces' => $b->spaces
                ->map(fn ($bs) => $bs->space?->name)
                ->filter()
                ->unique()
                ->join(', ') ?: '-',
            'client' => $b->client?->name ?? '-',
            'status' => $b->status?->value,
            'attendance' => $b->attendance_estimate ?? '-',
        ])->all();

        $byStatus = [];
        $totalAttendance = 0;
        foreach ($bookings as $b) {
            $status = $b->status?->value ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $totalAttendance += (int) $b->attendance_estimate;
        }

        $summary = [
            ['label' => 'Total events', 'value' => (string) $bookings->count()],
            ['label' => 'Total expected attendance', 'value' => number_format($totalAttendance)],
        ];
        foreach (BookingStatus::cases() as $status) {
            $count = $byStatus[$status->value] ?? 0;
            if ($count > 0) {
                $summary[] = [
                    'label' => ucfirst(str_replace('_', ' ', $status->value)),
                    'value' => (string) $count,
                ];
            }
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'day', 'label' => 'Day'],
                ['key' => 'time', 'label' => 'Start'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'spaces', 'label' => 'Spaces'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'attendance', 'label' => 'Att.', 'align' => 'right'],
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
