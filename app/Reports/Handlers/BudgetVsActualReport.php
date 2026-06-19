<?php

namespace App\Reports\Handlers;

use App\Models\FiscalYear;
use App\Models\Fund;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\BudgetService;
use App\Services\Accounting\ValueFormatter;

/**
 * Fiscal-year budget variance against actuals.
 * Pulls the live BudgetService summary so variance reflects current journal entries.
 */
class BudgetVsActualReport implements ReportHandler
{
    public function __construct(protected BudgetService $service) {}

    public function slug(): string
    {
        return 'budget-vs-actual';
    }

    public function category(): string
    {
        return 'Accounting';
    }

    public function title(): string
    {
        return 'Budget vs actual';
    }

    public function description(): string
    {
        return 'Per-account budgeted vs. actual amounts for a fiscal year, with variance and percent-used. Surfaces unbudgeted spend.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'fiscal_year_id', 'label' => 'Fiscal year', 'type' => 'select', 'options' => $this->yearOptions()],
            ['key' => 'fund_id', 'label' => 'Fund (optional)', 'type' => 'select', 'options' => $this->fundOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $yearId = isset($params['fiscal_year_id']) ? (int) $params['fiscal_year_id'] : null;
        $fundId = isset($params['fund_id']) ? (int) $params['fund_id'] : null;

        $year = $yearId !== null
            ? FiscalYear::query()->find($yearId)
            : FiscalYear::query()->open()->orderBy('starts_on')->first();

        if ($year === null) {
            return new ReportResult(
                title: $this->title(),
                description: 'No fiscal year configured. Create one under Admin -> Fiscal years.',
                columns: $this->columns(),
                rows: [],
                summary: [],
                generatedAt: now()->toIso8601String(),
            );
        }

        $summary = $this->service->summary($year, $fundId);
        $totals = $this->service->totalsByAccountType($year, $fundId);

        $rows = array_map(fn (array $row) => [
            'account_code' => $row['account_code'],
            'account_name' => $row['account_name'],
            'account_type' => $row['account_type'] ?? '-',
            'fund' => $row['fund_code'] ?? '-',
            'budgeted' => ValueFormatter::dollars($row['budgeted_cents']),
            'actual' => ValueFormatter::dollars($row['actual_cents']),
            'variance' => ValueFormatter::dollars($row['variance_cents']),
            'used_pct' => $row['used_pct'] !== null ? $row['used_pct'].'%' : '-',
            'flag' => $row['is_unbudgeted'] ? 'unbudgeted' : ($row['variance_cents'] < 0 ? 'over' : 'on track'),
        ], $summary);

        $summaryRows = [];
        foreach ($totals as $type => $t) {
            $summaryRows[] = [
                'label' => ucfirst($type).' variance',
                'value' => ValueFormatter::usdRounded($t['variance_cents']),
                'hint' => 'sum across all '.$type.' accounts',
            ];
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf(
                '%s (%s - %s)%s',
                $year->label,
                $year->starts_on->toFormattedDateString(),
                $year->ends_on->toFormattedDateString(),
                $fundId ? ' · fund filter applied' : '',
            ),
            columns: $this->columns(),
            rows: $rows,
            summary: $summaryRows,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, align?: string}>
     */
    protected function columns(): array
    {
        return [
            ['key' => 'account_code', 'label' => 'Code'],
            ['key' => 'account_name', 'label' => 'Account'],
            ['key' => 'account_type', 'label' => 'Type'],
            ['key' => 'fund', 'label' => 'Fund'],
            ['key' => 'budgeted', 'label' => 'Budgeted', 'align' => 'right'],
            ['key' => 'actual', 'label' => 'Actual', 'align' => 'right'],
            ['key' => 'variance', 'label' => 'Variance', 'align' => 'right'],
            ['key' => 'used_pct', 'label' => 'Used %', 'align' => 'right'],
            ['key' => 'flag', 'label' => 'Status'],
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function yearOptions(): array
    {
        return FiscalYear::query()
            ->orderByDesc('starts_on')
            ->get(['id', 'label'])
            ->map(fn ($y) => ['value' => (int) $y->id, 'label' => $y->label])
            ->all();
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function fundOptions(): array
    {
        return Fund::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($f) => ['value' => (int) $f->id, 'label' => $f->name])
            ->all();
    }
}
