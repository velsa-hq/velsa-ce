<?php

namespace App\Reports\Handlers;

use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Accounting\ValueFormatter;
use Carbon\CarbonImmutable;

/**
 * Balance sheet as of a date. Equity includes cumulative net income to date.
 * Built off the GL via FinancialStatementService so it ties to the trial balance.
 */
class BalanceSheetReport implements ReportHandler
{
    public function __construct(protected FinancialStatementService $service) {}

    public function slug(): string
    {
        return 'balance-sheet';
    }

    public function category(): string
    {
        return 'Accounting';
    }

    public function title(): string
    {
        return 'Balance sheet';
    }

    public function description(): string
    {
        return 'Assets, liabilities, and equity as of a date - equity includes net income to date. Ties to the trial balance.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'as_of', 'label' => 'As of', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue (optional)', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $asOf = ! empty($params['as_of'])
            ? CarbonImmutable::parse((string) $params['as_of'])
            : CarbonImmutable::now();
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $bs = $this->service->balanceSheet($asOf, $venueId);

        $rows = [];
        $this->appendSection($rows, 'Assets', $bs['assets'], $bs['assets_total_cents'], 'Total assets');
        $this->appendSection($rows, 'Liabilities', $bs['liabilities'], $bs['liabilities_total_cents'], 'Total liabilities');

        $equityRows = $bs['equity'];
        $equityRows[] = [
            'code' => '',
            'name' => 'Current period earnings',
            'balance_cents' => $bs['current_earnings_cents'],
        ];
        $this->appendSection($rows, 'Equity', $equityRows, $bs['equity_total_cents'], 'Total equity');

        $rows[] = [
            'section' => '',
            'code' => '',
            'account' => 'Total liabilities + equity',
            'amount' => ValueFormatter::dollars($bs['liabilities_total_cents'] + $bs['equity_total_cents']),
        ];

        return new ReportResult(
            title: $this->title(),
            description: 'As of '.$asOf->toFormattedDateString().($venueId ? ' · venue filter applied' : ''),
            columns: [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'code', 'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'right'],
            ],
            rows: $rows,
            summary: [
                ['label' => 'Total assets', 'value' => ValueFormatter::usd($bs['assets_total_cents'])],
                ['label' => 'Total liabilities + equity', 'value' => ValueFormatter::usd($bs['liabilities_total_cents'] + $bs['equity_total_cents'])],
                ['label' => 'Balanced', 'value' => $bs['balanced'] ? 'Yes' : 'No', 'hint' => 'assets = liabilities + equity'],
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
