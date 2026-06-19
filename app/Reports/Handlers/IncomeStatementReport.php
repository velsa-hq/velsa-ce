<?php

namespace App\Reports\Handlers;

use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\ValueFormatter;
use Carbon\CarbonImmutable;

/**
 * Income statement over a period (revenue - expense = net income), default YTD.
 */
class IncomeStatementReport implements ReportHandler
{
    public function __construct(protected FinancialStatementService $service) {}

    public function slug(): string
    {
        return 'income-statement';
    }

    public function category(): string
    {
        return 'Accounting';
    }

    public function title(): string
    {
        return 'Income statement';
    }

    public function description(): string
    {
        return 'Revenue and expenses over a period, with net income. Defaults to year-to-date.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->startOfYear()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue (optional)', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = ! empty($params['from'])
            ? CarbonImmutable::parse((string) $params['from'])
            : CarbonImmutable::now()->startOfYear();
        $to = ! empty($params['to'])
            ? CarbonImmutable::parse((string) $params['to'])
            : CarbonImmutable::now();
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $is = $this->service->incomeStatement($from, $to, $venueId);

        $rows = [];
        $this->appendSection($rows, 'Revenue', $is['revenue'], $is['revenue_total_cents'], 'Total revenue');
        $this->appendSection($rows, 'Expenses', $is['expenses'], $is['expense_total_cents'], 'Total expenses');
        $rows[] = [
            'section' => '',
            'code' => '',
            'account' => 'Net income',
            'amount' => ValueFormatter::dollars($is['net_income_cents']),
        ];

        return new ReportResult(
            title: $this->title(),
            description: sprintf(
                '%s - %s%s',
                $from->toFormattedDateString(),
                $to->toFormattedDateString(),
                $venueId ? ' · venue filter applied' : '',
            ),
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Total revenue', 'value' => ValueFormatter::usd($is['revenue_total_cents'])],
                ['label' => 'Total expenses', 'value' => ValueFormatter::usd($is['expense_total_cents'])],
                ['label' => 'Net income', 'value' => ValueFormatter::usd($is['net_income_cents'])],
            ],
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<array{code: string, name: string, balance_cents: int}>  $accounts
     */
    private function appendSection(array &$rows, string $section, array $accounts, int $total, string $totalLabel): void
    {
        foreach ($accounts as $a) {
            $rows[] = [
                'section' => $section,
                'code' => $a['code'],
                'account' => $a['name'],
                'amount' => ValueFormatter::dollars($a['balance_cents']),
            ];
        }

        $rows[] = [
            'section' => $section,
            'code' => '',
            'account' => $totalLabel,
            'amount' => ValueFormatter::dollars($total),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function venueOptions(): array
    {
        return Venue::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['value' => (int) $v->id, 'label' => $v->name])
            ->all();
    }
}
