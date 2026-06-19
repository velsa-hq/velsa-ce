<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Fund;
use App\Services\Accounting\BudgetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function index(): Response
    {
        $years = FiscalYear::query()
            ->withCount('budgets')
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (FiscalYear $y) => [
                'id' => $y->id,
                'label' => $y->label,
                'starts_on' => $y->starts_on?->toDateString(),
                'ends_on' => $y->ends_on?->toDateString(),
                'is_closed' => $y->is_closed,
                'closed_at' => $y->closed_at?->toIso8601String(),
                'budgets_count' => $y->budgets_count ?? 0,
            ]);

        return Inertia::render('admin/fiscal-years/index', [
            'years' => $years,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:16', 'unique:fiscal_years,label'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ]);

        FiscalYear::query()->create($data);

        return back()->with('toast', ['type' => 'success', 'message' => "Created fiscal year {$data['label']}."]);
    }

    public function show(FiscalYear $year, BudgetService $service): Response
    {
        $summary = $service->summary($year);
        $totals = $service->totalsByAccountType($year);

        $accounts = ChartOfAccount::query()
            ->postable()
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type']);

        $funds = Fund::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('admin/fiscal-years/show', [
            'year' => [
                'id' => $year->id,
                'label' => $year->label,
                'starts_on' => $year->starts_on?->toDateString(),
                'ends_on' => $year->ends_on?->toDateString(),
                'is_closed' => $year->is_closed,
                'closed_at' => $year->closed_at?->toIso8601String(),
            ],
            'summary' => $summary,
            'totals_by_type' => $totals,
            'accounts' => $accounts->map(fn (ChartOfAccount $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'account_type' => $a->account_type?->value,
            ]),
            'funds' => $funds->map(fn (Fund $f) => [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
            ]),
        ]);
    }

    public function storeBudget(Request $request, FiscalYear $year, BudgetService $service): RedirectResponse
    {
        $data = $request->validate([
            'chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'amount_cents' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $account = ChartOfAccount::query()->findOrFail($data['chart_of_account_id']);

        try {
            $service->setBudget(
                year: $year,
                account: $account,
                amountCents: (int) $data['amount_cents'],
                fundId: $data['fund_id'] ?? null,
                userId: $request->user()?->id,
                notes: $data['notes'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Set budget for {$account->code} {$account->name}.",
        ]);
    }

    public function close(Request $request, FiscalYear $year, BudgetService $service): RedirectResponse
    {
        $service->close($year, $request->user()?->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$year->label} closed."]);
    }

    public function reopen(Request $request, FiscalYear $year, BudgetService $service): RedirectResponse
    {
        $service->reopen($year, $request->user()?->id);

        return back()->with('toast', ['type' => 'success', 'message' => "{$year->label} reopened."]);
    }

    public function destroyBudget(Request $request, FiscalYear $year, Budget $budget, BudgetService $service): RedirectResponse
    {
        abort_unless($budget->fiscal_year_id === $year->id, 404);

        try {
            $service->deleteBudget($year, $budget, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return back()->with('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Budget line removed.']);
    }
}
