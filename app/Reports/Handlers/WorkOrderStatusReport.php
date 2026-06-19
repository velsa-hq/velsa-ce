<?php

namespace App\Reports\Handlers;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\ValueFormatter;

/**
 * Work order status + costs report.
 */
class WorkOrderStatusReport implements ReportHandler
{
    public function slug(): string
    {
        return 'work-order-status';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Work order status & costs';
    }

    public function description(): string
    {
        return 'All work orders with status, scheduled date, overdue flag, and accumulated labor + materials cost.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function run(array $params): ReportResult
    {
        $workOrders = WorkOrder::query()
            ->with(['venue:id,name', 'assignee:id,email'])
            ->orderBy('scheduled_for')
            ->get();

        $rows = $workOrders->map(fn (WorkOrder $wo) => [
            'reference' => $wo->reference,
            'title' => $wo->title,
            'kind' => $wo->kind?->value,
            'venue' => $wo->venue?->name ?? '-',
            'assignee' => $wo->assignee?->email ?? 'unassigned',
            'status' => $wo->status?->value,
            'priority' => $wo->priority,
            'scheduled_for' => $wo->scheduled_for?->format('M j, Y') ?? '-',
            'overdue' => $wo->isOverdue() ? 'YES' : '',
            'cost_dollars' => ValueFormatter::dollars($wo->cost_cents),
        ])->all();

        $byStatus = [];
        $totalCost = 0;
        $overdueCount = 0;
        $overdueCost = 0;
        foreach ($workOrders as $wo) {
            $status = $wo->status?->value ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $totalCost += $wo->cost_cents;
            if ($wo->isOverdue()) {
                $overdueCount++;
                $overdueCost += $wo->cost_cents;
            }
        }

        $summary = [
            ['label' => 'Total work orders', 'value' => (string) $workOrders->count()],
            ['label' => 'Total cost', 'value' => ValueFormatter::usdRounded($totalCost)],
            ['label' => 'Overdue', 'value' => (string) $overdueCount, 'hint' => $overdueCount > 0 ? 'needs attention' : null],
        ];
        foreach (WorkOrderStatus::cases() as $status) {
            $count = $byStatus[$status->value] ?? 0;
            if ($count > 0) {
                $summary[] = ['label' => ucfirst(str_replace('_', ' ', $status->value)), 'value' => (string) $count];
            }
        }

        return new ReportResult(
            title: $this->title(),
            description: 'Open + completed work orders with cost rollup',
            columns: [
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'title', 'label' => 'Title'],
                ['key' => 'kind', 'label' => 'Kind'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'assignee', 'label' => 'Assignee'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'priority', 'label' => 'P', 'align' => 'right'],
                ['key' => 'scheduled_for', 'label' => 'Scheduled'],
                ['key' => 'overdue', 'label' => '!'],
                ['key' => 'cost_dollars', 'label' => 'Cost $', 'align' => 'right'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }
}
