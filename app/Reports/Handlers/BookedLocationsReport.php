<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\ValueFormatter;
use Illuminate\Support\Carbon;

/**
 * Booked locations for a date range across all venues, by booking status.
 */
class BookedLocationsReport implements ReportHandler
{
    public function slug(): string
    {
        return 'booked-locations';
    }

    public function category(): string
    {
        return 'Scheduling';
    }

    public function title(): string
    {
        return 'Booked locations';
    }

    public function description(): string
    {
        return 'Every booking placed on a specific space within a date range, grouped by venue. Status badges follow the booking lifecycle (inquiry -> definite -> completed).';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->subDays(7)->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(60)->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from']) : now()->subDays(7);
        $to = isset($params['to']) ? Carbon::parse($params['to']) : now()->addDays(60);
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $bookings = Booking::query()
            ->with(['venue:id,name,slug', 'client:id,name', 'spaces.space:id,name'])
            ->whereBetween('start_at', [$from, $to])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->orderBy('start_at')
            ->get();

        $rows = $bookings->map(fn (Booking $b) => [
            'reference' => $b->reference,
            'name' => $b->name,
            'venue' => $b->venue?->name ?? '-',
            'spaces' => $b->spaces->map(fn ($bs) => $bs->space?->name)->filter()->implode(', '),
            'client' => $b->client?->name ?? '-',
            'status' => $b->status?->value,
            'start_at' => $b->start_at?->format('M j, Y g:i A'),
            'end_at' => $b->end_at?->format('M j, Y g:i A'),
            'attendance_estimate' => $b->attendance_estimate ?? '-',
            'total_dollars' => ValueFormatter::dollars($b->total_cents),
        ])->all();

        $byStatus = [];
        $totalValueCents = 0;
        foreach ($bookings as $b) {
            $status = $b->status?->value ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $totalValueCents += $b->total_cents;
        }

        $summary = [
            ['label' => 'Total bookings', 'value' => (string) $bookings->count()],
            ['label' => 'Total value', 'value' => ValueFormatter::usdRounded($totalValueCents)],
        ];
        foreach (BookingStatus::cases() as $status) {
            $count = $byStatus[$status->value] ?? 0;
            if ($count > 0) {
                $summary[] = ['label' => ucfirst(str_replace('_', ' ', $status->value)), 'value' => (string) $count];
            }
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'spaces', 'label' => 'Spaces'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'start_at', 'label' => 'Start'],
                ['key' => 'end_at', 'label' => 'End'],
                ['key' => 'attendance_estimate', 'label' => 'Att.', 'align' => 'right'],
                ['key' => 'total_dollars', 'label' => 'Total $', 'align' => 'right'],
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
