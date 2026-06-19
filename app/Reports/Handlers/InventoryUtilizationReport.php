<?php

namespace App\Reports\Handlers;

use App\Models\ResourceInventory;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;

/**
 * Total vs available per resource, flagging high-utilization items.
 */
class InventoryUtilizationReport implements ReportHandler
{
    public function slug(): string
    {
        return 'inventory-utilization';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Inventory utilization';
    }

    public function description(): string
    {
        return 'Per-resource availability, deployed quantity, and utilization percentage. High-utilization rows flagged for replenishment.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function run(array $params): ReportResult
    {
        $items = ResourceInventory::query()
            ->with(['venue:id,name'])
            ->orderBy('venue_id')
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        $rows = $items->map(function (ResourceInventory $r) {
            $deployed = max(0, $r->quantity_total - $r->quantity_available);
            $pct = $r->quantity_total > 0
                ? (int) round(($deployed / $r->quantity_total) * 100)
                : 0;

            return [
                'venue' => $r->venue?->name ?? '-',
                'kind' => $r->kind,
                'sku' => $r->sku ?? '-',
                'name' => $r->name,
                'available' => $r->quantity_available,
                'deployed' => $deployed,
                'total' => $r->quantity_total,
                'utilization_pct' => $pct,
                'flag' => $pct >= 80 ? 'replenish' : ($pct >= 50 ? 'monitor' : ''),
            ];
        })->all();

        $totalItems = $items->count();
        $needsReplenish = collect($rows)->where('flag', 'replenish')->count();
        $totalDeployed = collect($rows)->sum('deployed');
        $totalTotal = collect($rows)->sum('total');
        $aggregatePct = $totalTotal > 0 ? (int) round(($totalDeployed / $totalTotal) * 100) : 0;

        $summary = [
            ['label' => 'Tracked SKUs', 'value' => (string) $totalItems],
            ['label' => 'Aggregate utilization', 'value' => $aggregatePct.'%'],
            ['label' => 'Replenish (≥80%)', 'value' => (string) $needsReplenish, 'hint' => $needsReplenish > 0 ? 'low stock' : null],
        ];

        return new ReportResult(
            title: $this->title(),
            description: 'Resource availability across all venues',
            columns: [
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'kind', 'label' => 'Kind'],
                ['key' => 'sku', 'label' => 'SKU'],
                ['key' => 'name', 'label' => 'Item'],
                ['key' => 'available', 'label' => 'Available', 'align' => 'right'],
                ['key' => 'deployed', 'label' => 'Deployed', 'align' => 'right'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
                ['key' => 'utilization_pct', 'label' => 'Util %', 'align' => 'right'],
                ['key' => 'flag', 'label' => 'Flag'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }
}
