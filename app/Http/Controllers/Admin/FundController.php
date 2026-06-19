<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundType;
use App\Http\Controllers\Controller;
use App\Models\Fund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage the funds catalog (separately-reported books sharing the chart of
 * accounts). Code freezes once entries reference it (entries denormalize the
 * code); funds with entries or children cannot be deleted, only retired.
 */
class FundController extends Controller
{
    public function index(Request $request): Response
    {
        $funds = Fund::query()
            ->withCount('journalEntries')
            ->orderBy('code')
            ->get()
            ->map(fn (Fund $f) => $this->present($f));

        return Inertia::render('admin/funds/index', [
            'funds' => $funds,
            'types' => array_map(
                fn (FundType $t) => ['value' => $t->value, 'label' => $t->label()],
                FundType::cases(),
            ),
            'parents' => $funds
                ->map(fn ($f) => ['id' => $f['id'], 'code' => $f['code'], 'name' => $f['name']])
                ->values(),
            'can_manage' => (bool) $request->user()?->hasVenuePermission('accounting.post_journal'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, null);

        Fund::query()->create($data);

        return back()->with('toast', ['type' => 'success', 'message' => "Fund {$data['code']} created."]);
    }

    public function update(Request $request, Fund $fund): RedirectResponse
    {
        $data = $this->validatePayload($request, $fund);

        $fund->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => "Fund {$fund->code} updated."]);
    }

    public function destroy(Fund $fund): RedirectResponse
    {
        if ($fund->journalEntries()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete a fund with journal entries - retire it with an active-to date instead.',
            ]);
        }
        if ($fund->children()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete a fund that still has child funds.',
            ]);
        }

        $code = $fund->code;
        $fund->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "Fund {$code} deleted."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?Fund $existing): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('funds', 'code')->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'fund_type' => ['required', Rule::enum(FundType::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'parent_fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
        ]);

        // Code freezes once entries reference it (they denormalize the code).
        if ($existing !== null
            && $existing->journalEntries()->exists()
            && $data['code'] !== $existing->code) {
            throw ValidationException::withMessages(['code' => 'Code is locked - this fund has journal entries.']);
        }

        if ($existing !== null && ! empty($data['parent_fund_id'])) {
            if ($this->wouldCycle($existing, (int) $data['parent_fund_id'])) {
                throw ValidationException::withMessages(['parent_fund_id' => 'That parent would create a cycle.']);
            }
        }

        return $data;
    }

    /**
     * True if making $parentId the parent of $fund would form a cycle.
     */
    private function wouldCycle(Fund $fund, int $parentId): bool
    {
        if ($parentId === $fund->id) {
            return true;
        }

        $descendants = [];
        $stack = $fund->children()->pluck('id')->all();
        while ($stack !== []) {
            $id = array_pop($stack);
            if (in_array($id, $descendants, true)) {
                continue;
            }
            $descendants[] = $id;
            $stack = array_merge(
                $stack,
                Fund::query()->where('parent_fund_id', $id)->pluck('id')->all(),
            );
        }

        return in_array($parentId, $descendants, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Fund $f): array
    {
        return [
            'id' => $f->id,
            'code' => $f->code,
            'name' => $f->name,
            'fund_type' => $f->fund_type?->value,
            'fund_type_label' => $f->fund_type?->label(),
            'description' => $f->description,
            'parent_fund_id' => $f->parent_fund_id,
            'is_active' => $f->isActive(),
            'active_from' => $f->active_from?->toDateString(),
            'active_to' => $f->active_to?->toDateString(),
            'journal_entries_count' => $f->journal_entries_count ?? 0,
        ];
    }
}
