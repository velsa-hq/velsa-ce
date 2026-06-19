<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesGoal;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-salesperson revenue goals, keyed by (user, year, month?); null month is annual.
 */
class SalesGoalController extends Controller
{
    public function index(): Response
    {
        $goals = SalesGoal::query()
            ->with('user:id,name')
            ->orderByDesc('year')
            ->orderBy('month')
            ->get()
            ->map(fn (SalesGoal $g) => [
                'id' => $g->id,
                'user_id' => $g->user_id,
                'user_name' => $g->user?->name,
                'year' => $g->year,
                'month' => $g->month,
                'target_cents' => $g->target_cents,
            ]);

        return Inertia::render('admin/sales-goals/index', [
            'goals' => $goals,
            'salespeople' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
            'current_year' => (int) now()->year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        SalesGoal::query()->updateOrCreate(
            ['user_id' => $data['user_id'], 'year' => $data['year'], 'month' => $data['month'] ?? null],
            ['target_cents' => (int) round(((float) $data['target_dollars']) * 100)],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Sales goal saved.']);
    }

    public function update(Request $request, SalesGoal $salesGoal): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $salesGoal->update([
            'user_id' => $data['user_id'],
            'year' => $data['year'],
            'month' => $data['month'] ?? null,
            'target_cents' => (int) round(((float) $data['target_dollars']) * 100),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sales goal updated.']);
    }

    public function destroy(SalesGoal $salesGoal): RedirectResponse
    {
        $salesGoal->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Sales goal removed.']);
    }

    /**
     * @return array{user_id:int, year:int, month:?int, target_dollars:string}
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'target_dollars' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
