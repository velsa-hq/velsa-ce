<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use DateTimeInterface;

/**
 * Balance sheet + income statement from the general ledger.
 *
 * Balance-sheet equity folds in unclosed net income (revenue - expense),
 * so Assets = Liabilities + Equity holds exactly given balanced entries.
 * Balances are normal-side positive: debit for asset/expense, credit for
 * liability/equity/revenue.
 */
class FinancialStatementService
{
    /**
     * @return array{
     *   as_of: string,
     *   assets: list<array{code: string, name: string, balance_cents: int}>,
     *   liabilities: list<array{code: string, name: string, balance_cents: int}>,
     *   equity: list<array{code: string, name: string, balance_cents: int}>,
     *   assets_total_cents: int,
     *   liabilities_total_cents: int,
     *   equity_accounts_total_cents: int,
     *   current_earnings_cents: int,
     *   equity_total_cents: int,
     *   balanced: bool,
     * }
     */
    public function balanceSheet(DateTimeInterface $asOf, ?int $venueId = null): array
    {
        $balances = $this->balancesByAccount(asOf: $asOf, venueId: $venueId);

        $assets = $this->section($balances, AccountType::Asset);
        $liabilities = $this->section($balances, AccountType::Liability);
        $equity = $this->section($balances, AccountType::Equity);

        $revenueTotal = $this->typeTotal($balances, AccountType::Revenue);
        $expenseTotal = $this->typeTotal($balances, AccountType::Expense);
        $currentEarnings = $revenueTotal - $expenseTotal;

        $assetsTotal = $this->sum($assets);
        $liabilitiesTotal = $this->sum($liabilities);
        $equityAccountsTotal = $this->sum($equity);
        $equityTotal = $equityAccountsTotal + $currentEarnings;

        return [
            'as_of' => $asOf->format('Y-m-d'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'assets_total_cents' => $assetsTotal,
            'liabilities_total_cents' => $liabilitiesTotal,
            'equity_accounts_total_cents' => $equityAccountsTotal,
            'current_earnings_cents' => $currentEarnings,
            'equity_total_cents' => $equityTotal,
            'balanced' => $assetsTotal === $liabilitiesTotal + $equityTotal,
        ];
    }

    /**
     * @return array{
     *   from: string,
     *   to: string,
     *   revenue: list<array{code: string, name: string, balance_cents: int}>,
     *   expenses: list<array{code: string, name: string, balance_cents: int}>,
     *   revenue_total_cents: int,
     *   expense_total_cents: int,
     *   net_income_cents: int,
     * }
     */
    public function incomeStatement(DateTimeInterface $from, DateTimeInterface $to, ?int $venueId = null): array
    {
        $balances = $this->balancesByAccount(from: $from, to: $to, venueId: $venueId);

        $revenue = $this->section($balances, AccountType::Revenue);
        $expenses = $this->section($balances, AccountType::Expense);

        $revenueTotal = $this->sum($revenue);
        $expenseTotal = $this->sum($expenses);

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'revenue' => $revenue,
            'expenses' => $expenses,
            'revenue_total_cents' => $revenueTotal,
            'expense_total_cents' => $expenseTotal,
            'net_income_cents' => $revenueTotal - $expenseTotal,
        ];
    }

    /**
     * Normal-side-positive balance per account, keyed by code. Pass an
     * as-of cutoff (balance sheet) or a from/to window (income statement).
     *
     * @return array<string, array{code: string, name: string, type: ?AccountType, balance_cents: int}>
     */
    private function balancesByAccount(
        ?DateTimeInterface $asOf = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $to = null,
        ?int $venueId = null,
    ): array {
        $totals = JournalEntry::query()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($asOf, fn ($q) => $q->whereDate('posted_on', '<=', $asOf))
            ->when($from, fn ($q) => $q->whereDate('posted_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('posted_on', '<=', $to))
            ->selectRaw('account_code, sum(debit_cents) as debits, sum(credit_cents) as credits')
            ->groupBy('account_code')
            ->toBase()
            ->get();

        $accounts = ChartOfAccount::query()->get()->keyBy('code');
        $out = [];

        foreach ($totals as $t) {
            $account = $accounts->get($t->account_code);
            $type = $account?->account_type;
            $net = (int) $t->debits - (int) $t->credits;

            // credit types flip sign so balance is normal-side positive
            $balance = in_array($type, [AccountType::Asset, AccountType::Expense], true)
                ? $net
                : -$net;

            $out[$t->account_code] = [
                'code' => $t->account_code,
                'name' => $account !== null ? $account->name : $t->account_code,
                'type' => $type,
                'balance_cents' => $balance,
            ];
        }

        return $out;
    }

    /**
     * Non-zero accounts of one type, sorted by code.
     *
     * @param  array<string, array{code: string, name: string, type: ?AccountType, balance_cents: int}>  $balances
     * @return list<array{code: string, name: string, balance_cents: int}>
     */
    private function section(array $balances, AccountType $type): array
    {
        $rows = [];

        foreach ($balances as $row) {
            if ($row['type'] === $type && $row['balance_cents'] !== 0) {
                $rows[] = [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'balance_cents' => $row['balance_cents'],
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['code'], $b['code']));

        return $rows;
    }

    /**
     * @param  array<string, array{type: ?AccountType, balance_cents: int}>  $balances
     */
    private function typeTotal(array $balances, AccountType $type): int
    {
        $sum = 0;

        foreach ($balances as $row) {
            if ($row['type'] === $type) {
                $sum += $row['balance_cents'];
            }
        }

        return $sum;
    }

    /**
     * @param  list<array{balance_cents: int}>  $rows
     */
    private function sum(array $rows): int
    {
        return array_sum(array_column($rows, 'balance_cents'));
    }
}
