<?php

namespace App\Reports\Handlers;

use App\Enums\ExhibitorOrderStatus;
use App\Models\ExhibitorOrder;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;

/**
 * AR aging: open exhibitor balances bucketed 0/30/60/90+ by days since placed.
 * Partial payments show only the remaining balance.
 */
class ArAgingReport implements ReportHandler
{
    public function slug(): string
    {
        return 'ar-aging';
    }

    public function category(): string
    {
        return 'Accounting';
    }

    public function title(): string
    {
        return 'AR aging';
    }

    public function description(): string
    {
        return 'Open exhibitor balances bucketed by days since the order was placed: current / 1-30 / 31-60 / 61-90 / 90+ days outstanding.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function run(array $params): ReportResult
    {
        $now = now();
        $openStatuses = [
            ExhibitorOrderStatus::Pending->value,
            ExhibitorOrderStatus::PartiallyPaid->value,
        ];

        $orders = ExhibitorOrder::query()
            ->with(['exhibitor:id,company_name,email'])
            ->whereIn('status', $openStatuses)
            ->get();

        $buckets = ['current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];

        $rows = $orders->map(function (ExhibitorOrder $o) use ($now, &$buckets) {
            $balance = max(0, $o->total_cents - $o->paid_cents);
            $age = $o->placed_at?->diffInDays($now) ?? 0;
            $bucket = match (true) {
                $age < 1 => 'current',
                $age <= 30 => '1-30',
                $age <= 60 => '31-60',
                $age <= 90 => '61-90',
                default => '90+',
            };
            $buckets[$bucket] += $balance;

            return [
                'order_number' => $o->order_number,
                'company' => $o->exhibitor?->company_name ?? '-',
                'email' => $o->exhibitor?->email ?? '-',
                'placed_on' => $o->placed_at?->toDateString() ?? '-',
                'days_old' => (int) $age,
                'total_dollars' => number_format($o->total_cents / 100, 2),
                'paid_dollars' => number_format($o->paid_cents / 100, 2),
                'balance_dollars' => number_format($balance / 100, 2),
                'bucket' => $bucket,
            ];
        })->sortByDesc('days_old')->values()->all();

        $summary = [
            ['label' => 'Open orders', 'value' => (string) count($rows)],
            ['label' => 'Total outstanding', 'value' => '$'.number_format(array_sum($buckets) / 100, 2)],
            ['label' => 'Current', 'value' => '$'.number_format($buckets['current'] / 100, 2)],
            ['label' => '1-30 days', 'value' => '$'.number_format($buckets['1-30'] / 100, 2)],
            ['label' => '31-60 days', 'value' => '$'.number_format($buckets['31-60'] / 100, 2)],
            ['label' => '61-90 days', 'value' => '$'.number_format($buckets['61-90'] / 100, 2)],
            ['label' => '91+ days', 'value' => '$'.number_format($buckets['90+'] / 100, 2), 'hint' => 'collection risk'],
        ];

        return new ReportResult(
            title: $this->title(),
            description: 'Outstanding exhibitor order balances by age',
            columns: [
                ['key' => 'order_number', 'label' => 'Order'],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'email', 'label' => 'Contact'],
                ['key' => 'placed_on', 'label' => 'Placed'],
                ['key' => 'days_old', 'label' => 'Days', 'align' => 'right'],
                ['key' => 'total_dollars', 'label' => 'Total $', 'align' => 'right'],
                ['key' => 'paid_dollars', 'label' => 'Paid $', 'align' => 'right'],
                ['key' => 'balance_dollars', 'label' => 'Balance $', 'align' => 'right'],
                ['key' => 'bucket', 'label' => 'Age'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }
}
