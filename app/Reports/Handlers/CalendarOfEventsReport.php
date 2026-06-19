<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\DateFormatter;
use Illuminate\Support\Carbon;

/**
 * Forward-looking calendar of events with day-of-week emphasis. Sibling of
 * event-schedule but tuned for an at-a-glance read: only confirmed-ish events,
 * a longer default window, no tentative noise. The day-of-week column makes it
 * scan like a calendar even in tabular form.
 */
class CalendarOfEventsReport implements ReportHandler
{
    public function slug(): string
    {
        return 'calendar-of-events';
    }

    public function category(): string
    {
        return 'Scheduling';
    }

    public function title(): string
    {
        return 'Calendar of events';
    }

    public function description(): string
    {
        return 'Forward-looking calendar of confirmed and completed events in the window. Day of week + date emphasized so the list scans like a calendar.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(60)->toDateString()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->addDays(60)->endOfDay();

        $bookings = Booking::query()
            ->with(['venue:id,name', 'client:id,name'])
            ->whereBetween('start_at', [$from, $to])
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
            ])
            ->orderBy('start_at')
            ->get();

        $rows = $bookings->map(fn (Booking $b) => [
            'day' => $b->start_at?->format('l'),
            'date' => $b->start_at?->format('M j, Y'),
            'time' => DateFormatter::timeOnly($b->start_at),
            'name' => $b->name,
            'venue' => $b->venue?->name ?? '-',
            'client' => $b->client?->name ?? '-',
            'reference' => $b->reference,
        ])->all();

        // Weekday histogram - useful for spotting heavy days.
        $weekdayCounts = [];
        foreach ($bookings as $b) {
            $day = $b->start_at?->format('l') ?? 'Unknown';
            $weekdayCounts[$day] = ($weekdayCounts[$day] ?? 0) + 1;
        }

        $summary = [
            ['label' => 'Total events', 'value' => (string) $bookings->count()],
            ['label' => 'Days covered', 'value' => (string) (max(1, $from->diffInDays($to)) + 1)],
        ];
        $weekdayOrder = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($weekdayOrder as $day) {
            $count = $weekdayCounts[$day] ?? 0;
            if ($count > 0) {
                $summary[] = ['label' => $day, 'value' => (string) $count];
            }
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'day', 'label' => 'Day'],
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'time', 'label' => 'Start'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'reference', 'label' => 'Ref'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }
}
