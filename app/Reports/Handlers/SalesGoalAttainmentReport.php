<?php

namespace App\Reports\Handlers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\SalesGoal;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Per-salesperson sales goals vs. actual. Actuals are the booked revenue
 * (definite + completed) attributed to each salesperson (booking owner) within
 * the goal's period. Annual goals (month = null) span the year; monthly goals
 * span that month.
 */
class SalesGoalAttainmentReport implements ReportHandler
{
    private const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    public function slug(): string
    {
        return 'sales-goal-attainment';
    }

    public function category(): string
    {
        return 'Sales';
    }

    public function title(): string
    {
        return 'Sales goal attainment';
    }

    public function description(): string
    {
        return 'Per-salesperson revenue goals vs. actual booked revenue (definite + completed) for the period, with variance and attainment percentage.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'year', 'label' => 'Year', 'type' => 'number', 'default' => (string) now()->year],
        ];
    }

    public function run(array $params): ReportResult
    {
        $year = isset($params['year']) ? (int) $params['year'] : (int) now()->year;

        $goals = SalesGoal::query()
            ->with('user:id,name')
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        $totalTarget = 0;
        $totalActual = 0;

        $rows = $goals->map(function (SalesGoal $goal) use (&$totalTarget, &$totalActual) {
            [$from, $to] = $this->periodBounds($goal);

            $actual = (int) Booking::query()
                ->where('owner_user_id', $goal->user_id)
                ->whereIn('status', [BookingStatus::Definite->value, BookingStatus::Completed->value])
                ->whereBetween('start_at', [$from, $to])
                ->sum('total_cents');

            $target = $goal->target_cents;
            $totalTarget += $target;
            $totalActual += $actual;

            $variance = $actual - $target;
            $attainment = $target > 0 ? round($actual / $target * 100, 1) : null;

            return [
                'salesperson' => $goal->user->name,
                'period' => $goal->month === null ? "FY {$goal->year}" : self::MONTHS[$goal->month]." {$goal->year}",
                'target' => $this->usd($target),
                'actual' => $this->usd($actual),
                'variance' => ($variance >= 0 ? '+' : '-').$this->usd(abs($variance)),
                'attainment' => $attainment !== null ? $attainment.'%' : '-',
            ];
        })->all();

        $overallAttainment = $totalTarget > 0 ? round($totalActual / $totalTarget * 100, 1).'%' : '-';

        return new ReportResult(
            title: $this->title(),
            description: "Sales goals for {$year}",
            columns: [
                ['key' => 'salesperson', 'label' => 'Salesperson'],
                ['key' => 'period', 'label' => 'Period'],
                ['key' => 'target', 'label' => 'Goal', 'align' => 'right'],
                ['key' => 'actual', 'label' => 'Actual', 'align' => 'right'],
                ['key' => 'variance', 'label' => 'Variance', 'align' => 'right'],
                ['key' => 'attainment', 'label' => 'Attainment', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Goals', 'value' => (string) $goals->count()],
                ['label' => 'Total goal', 'value' => $this->usd($totalTarget)],
                ['label' => 'Total actual', 'value' => $this->usd($totalActual)],
                ['label' => 'Overall attainment', 'value' => $overallAttainment],
            ],
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodBounds(SalesGoal $goal): array
    {
        if ($goal->month === null) {
            return [
                Carbon::create($goal->year, 1, 1)->startOfDay(),
                Carbon::create($goal->year, 12, 31)->endOfDay(),
            ];
        }

        $start = Carbon::create($goal->year, $goal->month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()];
    }

    private function usd(int $cents): string
    {
        return '$'.number_format($cents / 100, 0);
    }
}
