<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevenueTrendTile extends DashboardTile
{
    public function key(): string
    {
        return 'revenue_trend';
    }

    public function label(): string
    {
        return 'Revenue trend (12 months)';
    }

    public function description(): string
    {
        return 'Monthly booking-revenue total over the trailing 12 months - definite + completed + tentative bookings.';
    }

    public function columnSpan(): int
    {
        return 8;
    }

    public function permission(): ?string
    {
        return 'reports.view';
    }

    public function render(User $user): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(11);
        $end = now()->copy()->endOfMonth();

        $monthExpr = DB::connection()->getDriverName() === 'pgsql'
            ? "to_char(start_at, 'YYYY-MM')"
            : "strftime('%Y-%m', start_at)";

        $byMonth = Booking::query()
            ->whereIn('status', [
                BookingStatus::Definite->value,
                BookingStatus::Completed->value,
                BookingStatus::Tentative->value,
            ])
            ->whereBetween('start_at', [$start, $end])
            ->selectRaw("{$monthExpr} as month, sum(total_cents) as total_cents, count(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $points = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $key = $cursor->format('Y-m');
            $row = $byMonth->get($key);
            $points[] = [
                'month' => $key,
                'label' => $cursor->format('M'),
                'total_cents' => (int) ($row->total_cents ?? 0),
                'count' => (int) ($row->count ?? 0),
            ];
            $cursor = $cursor->copy()->addMonth();
        }

        return ['points' => $points];
    }
}
