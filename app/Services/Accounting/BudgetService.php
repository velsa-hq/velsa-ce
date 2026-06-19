<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Fund;
use App\Models\JournalEntry;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Budget operations + variance computation against actuals.
 *
 * Actuals are read live from journal_entries; no materialized rollup
 * table (single source of truth, budgets queried on demand).
 */
class BudgetService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Insert-or-update a budget line. Idempotent on the
     * (fiscal_year, account, fund) triple.
     */
    public function setBudget(
        FiscalYear $year,
        ChartOfAccount $account,
        int $amountCents,
        ?int $fundId = null,
        ?int $userId = null,
        ?string $notes = null,
    ): Budget {
        if (! $account->isPostable()) {
            throw new RuntimeException(
                "Account '{$account->code}' is a roll-up (is_postable=false); budgets must target a leaf account."
            );
        }
        if ($year->is_closed) {
            throw new RuntimeException(
                "Fiscal year '{$year->label}' is closed; budget edits are not permitted."
            );
        }

        $existing = Budget::query()
            ->where('fiscal_year_id', $year->id)
            ->where('chart_of_account_id', $account->id)
            ->where('fund_id', $fundId)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'amount_cents' => $amountCents,
                'notes' => $notes,
                'updated_by_user_id' => $userId,
            ]);

            return $existing->fresh();
        }

        return Budget::query()->create([
            'fiscal_year_id' => $year->id,
            'chart_of_account_id' => $account->id,
            'fund_id' => $fundId,
            'amount_cents' => $amountCents,
            'notes' => $notes,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ])->fresh();
    }

    /**
     * Remove a budget line. Refused while the fiscal year is closed.
     */
    public function deleteBudget(FiscalYear $year, Budget $budget, ?int $userId = null): void
    {
        if ($year->is_closed) {
            throw new RuntimeException(
                "Fiscal year '{$year->label}' is closed; budget edits are not permitted."
            );
        }

        $this->auditLogger->record(
            eventType: 'budget.deleted',
            subject: $budget,
            payload: [
                'fiscal_year_id' => $year->id,
                'chart_of_account_id' => $budget->chart_of_account_id,
                'fund_id' => $budget->fund_id,
                'amount_cents' => $budget->amount_cents,
                'deleted_by_user_id' => $userId,
            ],
        );

        $budget->delete();
    }

    /**
     * Budget-vs-actual variance summary for a fiscal year.
     *
     * Actual = natural-balance side per account type (debit for
     * asset/expense, credit for liability/equity/revenue). Variance
     * sign favors under-spend for asset/expense, over-earn for the rest;
     * see actualForAccount() and variance().
     *
     * @return array<int, array<string, mixed>>
     */
    public function summary(FiscalYear $year, ?int $fundId = null): array
    {
        $budgets = $year->budgets()
            ->with(['account', 'fund'])
            ->when($fundId, fn ($q, $v) => $q->where('fund_id', $v))
            ->get()
            ->keyBy(fn (Budget $b) => $this->budgetKey($b->chart_of_account_id, $b->fund_id));

        $actuals = JournalEntry::query()
            ->whereBetween('posted_on', [$year->starts_on->toDateString(), $year->ends_on->toDateString()])
            ->when($fundId, fn ($q, $v) => $q->where('fund_id', $v))
            ->groupBy('chart_of_account_id', 'fund_id')
            ->selectRaw('chart_of_account_id, fund_id, sum(debit_cents) as debit_sum, sum(credit_cents) as credit_sum')
            ->get();

        // union both sides so unbudgeted spend still surfaces
        $keys = collect();
        foreach ($budgets as $key => $b) {
            $keys->push(['key' => $key, 'account_id' => $b->chart_of_account_id, 'fund_id' => $b->fund_id]);
        }
        foreach ($actuals as $a) {
            $k = $this->budgetKey($a->chart_of_account_id, $a->fund_id);
            if (! $keys->firstWhere('key', $k)) {
                $keys->push(['key' => $k, 'account_id' => $a->chart_of_account_id, 'fund_id' => $a->fund_id]);
            }
        }

        $accountIds = $keys->pluck('account_id')->unique()->all();
        $fundIds = $keys->pluck('fund_id')->filter()->unique()->all();
        $accounts = ChartOfAccount::query()->whereIn('id', $accountIds)->get()->keyBy('id');
        $funds = Fund::query()->whereIn('id', $fundIds)->get()->keyBy('id');

        $rows = [];
        foreach ($keys as $entry) {
            $account = $accounts->get($entry['account_id']);
            if ($account === null) {
                continue;
            }
            $fund = $entry['fund_id'] ? $funds->get($entry['fund_id']) : null;
            $key = $entry['key'];

            $budget = $budgets->get($key);
            $actualRow = $actuals->first(
                fn ($a) => $a->chart_of_account_id === $entry['account_id']
                    && (int) $a->fund_id === (int) $entry['fund_id'],
            );

            $budgetedCents = (int) ($budget?->amount_cents ?? 0);
            $actualCents = $this->actualForAccount(
                $account,
                (int) ($actualRow->debit_sum ?? 0),
                (int) ($actualRow->credit_sum ?? 0),
            );

            $variance = $this->variance($account, $budgetedCents, $actualCents);
            $usedPct = $budgetedCents > 0
                ? round(abs($actualCents) / $budgetedCents * 100, 1)
                : null;

            $rows[] = [
                'budget_id' => $budget?->id,
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->account_type?->value,
                'fund_id' => $entry['fund_id'],
                'fund_code' => $fund?->code,
                'fund_name' => $fund?->name,
                'budgeted_cents' => $budgetedCents,
                'actual_cents' => $actualCents,
                'variance_cents' => $variance,
                'used_pct' => $usedPct,
                'is_unbudgeted' => $budget === null,
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['account_code'], $b['account_code']));

        return $rows;
    }

    /**
     * Fiscal-year rollups grouped by account type.
     *
     * @return array<string, array{budgeted_cents: int, actual_cents: int, variance_cents: int}>
     */
    public function totalsByAccountType(FiscalYear $year, ?int $fundId = null): array
    {
        $summary = $this->summary($year, $fundId);

        $totals = [];
        foreach ($summary as $row) {
            $type = $row['account_type'] ?? 'unknown';
            $totals[$type] ??= ['budgeted_cents' => 0, 'actual_cents' => 0, 'variance_cents' => 0];
            $totals[$type]['budgeted_cents'] += $row['budgeted_cents'];
            $totals[$type]['actual_cents'] += $row['actual_cents'];
            $totals[$type]['variance_cents'] += $row['variance_cents'];
        }

        return $totals;
    }

    /**
     * Close a fiscal year; its budgets become read-only after this.
     */
    public function close(FiscalYear $year, ?int $userId = null): FiscalYear
    {
        if ($year->is_closed) {
            return $year;
        }

        $year->update([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by_user_id' => $userId,
        ]);

        $this->auditLogger->record(
            eventType: 'fiscal_year.closed',
            subject: $year->fresh(),
            payload: ['closed_by_user_id' => $userId],
        );

        return $year->fresh();
    }

    public function reopen(FiscalYear $year, ?int $userId = null): FiscalYear
    {
        if (! $year->is_closed) {
            return $year;
        }

        $year->update([
            'is_closed' => false,
            'closed_at' => null,
            'closed_by_user_id' => null,
        ]);

        $this->auditLogger->record(
            eventType: 'fiscal_year.reopened',
            subject: $year->fresh(),
            payload: ['reopened_by_user_id' => $userId],
        );

        return $year->fresh();
    }

    protected function budgetKey(int $accountId, ?int $fundId): string
    {
        return $accountId.':'.($fundId ?? 'null');
    }

    /**
     * Natural-balance side for an account, as a positive int.
     */
    protected function actualForAccount(ChartOfAccount $account, int $debitSum, int $creditSum): int
    {
        return match ($account->account_type) {
            AccountType::Asset, AccountType::Expense => $debitSum - $creditSum,
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => $creditSum - $debitSum,
            default => $debitSum - $creditSum,
        };
    }

    /**
     * Variance; positive = favorable (under-budget spend / over-budget earn).
     */
    protected function variance(ChartOfAccount $account, int $budgeted, int $actual): int
    {
        return match ($account->account_type) {
            // spend types: under is good
            AccountType::Asset, AccountType::Expense => $budgeted - $actual,
            // earning types: over is good
            AccountType::Liability, AccountType::Equity, AccountType::Revenue => $actual - $budgeted,
            default => $budgeted - $actual,
        };
    }
}
