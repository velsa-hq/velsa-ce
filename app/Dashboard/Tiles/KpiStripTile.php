<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\ContractStatus;
use App\Enums\ExhibitorOrderStatus;
use App\Models\Contract;
use App\Models\ExhibitorOrder;
use App\Models\Lead;
use App\Models\OutlineItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Accounting\ValueFormatter;

class KpiStripTile extends DashboardTile
{
    public function key(): string
    {
        return 'kpi_strip';
    }

    public function label(): string
    {
        return 'KPI strip';
    }

    public function description(): string
    {
        return 'Five-column row of headline numbers: open pipeline, AR outstanding, contracts in flight, overdue work orders, and today\'s outline items.';
    }

    public function columnSpan(): int
    {
        return 12;
    }

    public function render(User $user): array
    {
        $openLeads = Lead::query()->open()->get();
        $weightedPipelineCents = (int) $openLeads->sum(fn (Lead $l) => $l->weightedValueCents());
        $pipelineCount = $openLeads->count();

        $arOpen = ExhibitorOrder::query()
            ->whereIn('status', [ExhibitorOrderStatus::Pending->value, ExhibitorOrderStatus::PartiallyPaid->value])
            ->get();
        $arTotalCents = (int) $arOpen->sum(fn (ExhibitorOrder $o) => max(0, $o->total_cents - $o->paid_cents));
        $arNinetyPlus = (int) $arOpen
            ->filter(fn (ExhibitorOrder $o) => $o->placed_at && $o->placed_at->diffInDays(now()) > 90)
            ->sum(fn (ExhibitorOrder $o) => max(0, $o->total_cents - $o->paid_cents));

        $contractsInFlight = Contract::query()
            ->whereIn('status', [
                ContractStatus::Sent->value,
                ContractStatus::Viewed->value,
                ContractStatus::PartiallySigned->value,
            ])
            ->count();
        $contractsSentThisWeek = Contract::query()
            ->where('sent_at', '>=', now()->subDays(7))
            ->count();

        $overdueWorkOrders = WorkOrder::query()->overdue()->count();
        $totalOpenWorkOrders = WorkOrder::query()->open()->count();

        $todayItemsCount = OutlineItem::query()
            ->between(now()->startOfDay(), now()->endOfDay()->addSecond())
            ->count();

        return [
            'pipeline' => [
                'label' => 'Open pipeline',
                'value_cents' => $weightedPipelineCents,
                'value_display' => ValueFormatter::usdRounded($weightedPipelineCents),
                'sub' => "{$pipelineCount} open leads · weighted forecast",
            ],
            'ar' => [
                'label' => 'AR outstanding',
                'value_cents' => $arTotalCents,
                'value_display' => ValueFormatter::usdRounded($arTotalCents),
                'sub' => $arNinetyPlus > 0
                    ? ValueFormatter::usdRounded($arNinetyPlus).' over 90 days'
                    : 'no aged balances',
                'warning' => $arNinetyPlus > 0,
            ],
            'contracts' => [
                'label' => 'Contracts in flight',
                'value' => $contractsInFlight,
                'sub' => "{$contractsSentThisWeek} sent in last 7 days",
            ],
            'work_orders' => [
                'label' => 'Overdue work orders',
                'value' => $overdueWorkOrders,
                'sub' => "{$totalOpenWorkOrders} open total",
                'warning' => $overdueWorkOrders > 0,
            ],
            'outline' => [
                'label' => 'Today\'s outline items',
                'value' => $todayItemsCount,
                'sub' => 'scheduled across all events',
            ],
        ];
    }
}
