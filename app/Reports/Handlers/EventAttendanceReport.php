<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Estimate-vs-actual attendance analysis. The default window looks backward
 * (completed events) so the actual column is meaningful; flipping the dates
 * also works for forward planning where only the estimate is filled in.
 */
class EventAttendanceReport implements ReportHandler
{
    public function slug(): string
    {
        return 'event-attendance';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Event attendance';
    }

    public function description(): string
    {
        return 'Per-event estimate vs. actual attendance with variance. Useful for post-event review and capacity-planning calibration.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->subDays(90)->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->toDateString()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->subDays(90)->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->endOfDay();

        $bookings = Booking::query()
            ->with(['venue:id,name', 'client:id,name'])
            ->whereBetween('start_at', [$from, $to])
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
                BookingStatus::Tentative->value,
            ])
            ->orderBy('start_at')
            ->get();

        $totalEstimate = 0;
        $totalActual = 0;
        $rowsWithActual = 0;

        $rows = $bookings->map(function (Booking $b) use (&$totalEstimate, &$totalActual, &$rowsWithActual) {
            $estimate = (int) ($b->attendance_estimate ?? 0);
            $actual = $b->attendance_actual !== null ? (int) $b->attendance_actual : null;

            $totalEstimate += $estimate;
            if ($actual !== null) {
                $totalActual += $actual;
                $rowsWithActual++;
            }

            $variance = $actual !== null ? $actual - $estimate : null;
            $variancePct = $actual !== null && $estimate > 0
                ? round(($actual - $estimate) / $estimate * 100, 1)
                : null;

            return [
                'date' => $b->start_at?->format('M j, Y'),
                'reference' => $b->reference,
                'name' => $b->name,
                'venue' => $b->venue?->name ?? '-',
                'client' => $b->client?->name ?? '-',
                'status' => $b->status?->value,
                'estimate' => $estimate > 0 ? number_format($estimate) : '-',
                'actual' => $actual !== null ? number_format($actual) : '-',
                'variance' => $variance !== null ? ($variance >= 0 ? '+' : '').number_format($variance) : '-',
                'variance_pct' => $variancePct !== null ? ($variancePct >= 0 ? '+' : '').$variancePct.'%' : '-',
            ];
        })->all();

        $rowsWithActual > 0
            ? round(($totalActual - ($totalEstimate * ($rowsWithActual / max($bookings->count(), 1)))) / $rowsWithActual)
            : 0;

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'estimate', 'label' => 'Est.', 'align' => 'right'],
                ['key' => 'actual', 'label' => 'Actual', 'align' => 'right'],
                ['key' => 'variance', 'label' => 'Variance', 'align' => 'right'],
                ['key' => 'variance_pct', 'label' => '%', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Events in window', 'value' => (string) $bookings->count()],
                ['label' => 'Total estimated', 'value' => number_format($totalEstimate)],
                ['label' => 'Total actual', 'value' => number_format($totalActual)],
                ['label' => 'Events with actuals on file', 'value' => (string) $rowsWithActual],
            ],
            generatedAt: now()->toIso8601String(),
        );
    }
}
