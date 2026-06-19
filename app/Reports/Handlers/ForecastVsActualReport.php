<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\ValueFormatter;
use Illuminate\Support\Carbon;

/**
 * Forecast vs actual on the two dimensions a venue forecasts then measures:
 * revenue (quoted/estimated booking value vs what was invoiced) and attendance
 * (estimate vs actual). One row per event in the window, with per-dimension
 * variance plus window totals.
 */
class ForecastVsActualReport implements ReportHandler
{
    public function slug(): string
    {
        return 'forecast-vs-actual';
    }

    public function category(): string
    {
        return 'Sales';
    }

    public function title(): string
    {
        return 'Forecast vs actual';
    }

    public function description(): string
    {
        return 'Per-event forecast vs actual for revenue (quoted value vs invoiced) and attendance (estimate vs actual), with variance and window totals.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->subDays(90)->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue (optional)', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->subDays(90)->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->endOfDay();
        $venueId = isset($params['venue_id']) ? (int) $params['venue_id'] : null;

        $bookings = Booking::query()
            ->whereBetween('start_at', [$from, $to])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->whereIn('status', [
                BookingStatus::Tentative->value,
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
            ])
            ->orderBy('start_at')
            ->get();

        // resolve display names by id (avoids per-row relation hydration)
        $venueNames = Venue::query()->whereIn('id', $bookings->pluck('venue_id')->unique())
            ->pluck('name', 'id');
        $clientNames = Client::query()->whereIn('id', $bookings->pluck('client_id')->filter()->unique())
            ->pluck('name', 'id');

        $fcRevenue = 0;
        $acRevenue = 0;
        $fcAttendance = 0;
        $acAttendance = 0;

        $rows = $bookings->map(function (Booking $b) use ($venueNames, $clientNames, &$fcRevenue, &$acRevenue, &$fcAttendance, &$acAttendance) {
            $forecastCents = (int) $b->total_cents;
            $actualCents = $b->invoicedCents();
            $fcRevenue += $forecastCents;
            $acRevenue += $actualCents;

            $estAtt = (int) ($b->attendance_estimate ?? 0);
            $actAtt = $b->attendance_actual !== null ? (int) $b->attendance_actual : null;
            $fcAttendance += $estAtt;
            if ($actAtt !== null) {
                $acAttendance += $actAtt;
            }

            return [
                'date' => $b->start_at?->format('M j, Y'),
                'name' => $b->name,
                'venue' => $venueNames[$b->venue_id] ?? '-',
                'client' => $b->client_id !== null ? ($clientNames[$b->client_id] ?? '-') : '-',
                'status' => $b->status->value,
                'forecast_revenue' => ValueFormatter::usd($forecastCents),
                'actual_revenue' => ValueFormatter::usd($actualCents),
                'revenue_variance_pct' => $this->variancePct($forecastCents, $actualCents),
                'est_attendance' => $estAtt > 0 ? number_format($estAtt) : '-',
                'actual_attendance' => $actAtt !== null ? number_format($actAtt) : '-',
                'attendance_variance_pct' => $actAtt !== null ? $this->variancePct($estAtt, $actAtt) : '-',
            ];
        })->all();

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'name', 'label' => 'Event'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'forecast_revenue', 'label' => 'Forecast rev.', 'align' => 'right'],
                ['key' => 'actual_revenue', 'label' => 'Actual rev.', 'align' => 'right'],
                ['key' => 'revenue_variance_pct', 'label' => 'Rev. var.', 'align' => 'right'],
                ['key' => 'est_attendance', 'label' => 'Est. att.', 'align' => 'right'],
                ['key' => 'actual_attendance', 'label' => 'Actual att.', 'align' => 'right'],
                ['key' => 'attendance_variance_pct', 'label' => 'Att. var.', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Events in window', 'value' => (string) $bookings->count()],
                ['label' => 'Forecast revenue', 'value' => ValueFormatter::usd($fcRevenue)],
                ['label' => 'Actual (invoiced) revenue', 'value' => ValueFormatter::usd($acRevenue)],
                ['label' => 'Revenue variance', 'value' => $this->variancePct($fcRevenue, $acRevenue)],
                ['label' => 'Forecast attendance', 'value' => number_format($fcAttendance)],
                ['label' => 'Actual attendance', 'value' => number_format($acAttendance)],
            ],
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Signed percent change of actual vs forecast. "-" when there is no
     * forecast basis to compare against.
     */
    private function variancePct(int $forecast, int $actual): string
    {
        if ($forecast === 0) {
            return $actual === 0 ? '0%' : '-';
        }

        $pct = round(($actual - $forecast) / $forecast * 100, 1);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function venueOptions(): array
    {
        return Venue::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Venue $v) => ['value' => (int) $v->id, 'label' => $v->name])
            ->all();
    }
}
