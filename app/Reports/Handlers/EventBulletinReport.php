<?php

namespace App\Reports\Handlers;

use App\Models\Booking;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\DateFormatter;
use Carbon\CarbonImmutable;

/**
 * Daily ops bulletin. Lists every event on a given day (default today) with the
 * lean detail staff need for a printed or emailed handout: time, venue, client,
 * attendance, primary contact. Distinct from the window-spanning event-schedule.
 */
class EventBulletinReport implements ReportHandler
{
    public function slug(): string
    {
        return 'event-bulletin';
    }

    public function category(): string
    {
        return 'Scheduling';
    }

    public function title(): string
    {
        return 'Event bulletin';
    }

    public function description(): string
    {
        return 'A one-day ops bulletin: every booking happening on the chosen date, ordered by start time, with the essentials for staff distribution.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'default' => now()->toDateString()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $date = isset($params['date'])
            ? CarbonImmutable::parse($params['date'])
            : CarbonImmutable::today();
        $start = $date->startOfDay();
        $end = $date->endOfDay();

        $bookings = Booking::query()
            ->with(['venue:id,name', 'client:id,name', 'client.contacts'])
            ->whereBetween('start_at', [$start, $end])
            ->orderBy('start_at')
            ->get();

        $rows = $bookings->map(function (Booking $b) {
            $primary = $b->client?->contacts->firstWhere('is_primary', true);

            return [
                'time' => DateFormatter::timeOnly($b->start_at),
                'end_time' => DateFormatter::timeOnly($b->end_at),
                'reference' => $b->reference,
                'name' => $b->name,
                'venue' => $b->venue?->name ?? '-',
                'client' => $b->client?->name ?? '-',
                'contact' => $primary?->name ?? '-',
                'phone' => $primary?->phone ?? '-',
                'attendance' => $b->attendance_estimate ?? '-',
                'status' => $b->status?->value,
            ];
        })->all();

        $totalAttendance = (int) $bookings->sum('attendance_estimate');

        return new ReportResult(
            title: $this->title(),
            description: $date->format('l, F j, Y'),
            columns: [
                ['key' => 'time', 'label' => 'Start'],
                ['key' => 'end_time', 'label' => 'End'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'contact', 'label' => 'Primary contact'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'attendance', 'label' => 'Att.', 'align' => 'right'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Events today', 'value' => (string) $bookings->count()],
                ['label' => 'Total expected attendance', 'value' => number_format($totalAttendance)],
            ],
            generatedAt: now()->toIso8601String(),
        );
    }
}
