<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manage the chart of accounts - the whitelist of GL codes journal entries
 * post against. Code and type lock once an account has postings (changing them
 * would invalidate historical entries and the reports run off them); accounts
 * with entries or children can't be deleted - retire them with an active-to date.
 */
class ChartOfAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = ChartOfAccount::query()
            ->withCount('journalEntries')
            ->orderBy('code')
            ->get()
            ->map(fn (ChartOfAccount $a) => $this->present($a));

        return Inertia::render('admin/chart-of-accounts/index', [
            'accounts' => $accounts,
            'grouped' => $accounts->groupBy('account_type'),
            'types' => array_map(
                fn (AccountType $t) => ['value' => $t->value, 'label' => $t->label()],
                AccountType::cases(),
            ),
            'parents' => ChartOfAccount::query()
                ->where('is_postable', false)
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (ChartOfAccount $a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name]),
            'can_manage' => (bool) $request->user()?->hasVenuePermission('accounting.post_journal'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request, null);

        ChartOfAccount::query()->create($data);

        return back()->with('toast', ['type' => 'success', 'message' => "Account {$data['code']} created."]);
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $data = $this->validatePayload($request, $chartOfAccount);

        $chartOfAccount->update($data);

        return back()->with('toast', ['type' => 'success', 'message' => "Account {$chartOfAccount->code} updated."]);
    }

    public function destroy(ChartOfAccount $chartOfAccount): RedirectResponse
    {
        if ($chartOfAccount->journalEntries()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete an account with journal entries - retire it with an active-to date instead.',
            ]);
        }
        if ($chartOfAccount->children()->exists()) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Cannot delete an account that still has child accounts.',
            ]);
        }

        $code = $chartOfAccount->code;
        $chartOfAccount->delete();

        return back()->with('toast', ['type' => 'success', 'message' => "Account {$code} deleted."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?ChartOfAccount $existing): array
    {
        $hasEntries = $existing !== null && $existing->journalEntries()->exists();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('chart_of_accounts', 'code')->ignore($existing?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'account_subtype' => ['nullable', 'string', 'max:60'],
            'normal_balance' => ['nullable', Rule::in(['debit', 'credit'])],
            'parent_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_postable' => ['boolean'],
            'active_from' => ['nullable', 'date'],
            'active_to' => ['nullable', 'date', 'after_or_equal:active_from'],
        ]);

        // once an account carries postings, code and type are frozen -
        // changing them would orphan denormalized entry codes and break the
        // report math already run
        if ($hasEntries) {
            if ($data['code'] !== $existing->code) {
                throw ValidationException::withMessages(['code' => 'Code is locked - this account has journal entries.']);
            }
            if ($data['account_type'] !== $existing->account_type?->value) {
                throw ValidationException::withMessages(['account_type' => 'Type is locked - this account has journal entries.']);
            }
        }

        // prevent hierarchy cycles: a parent can't be the account itself or a
        // descendant
        if ($existing !== null && ! empty($data['parent_account_id'])) {
            if ($this->wouldCycle($existing, (int) $data['parent_account_id'])) {
                throw ValidationException::withMessages(['parent_account_id' => 'That parent would create a cycle.']);
            }
        }

        $data['is_postable'] = $request->boolean('is_postable');

        return $data;
    }

    /**
     * True if making $parentId the parent of $account would form a cycle -
     * i.e. $parentId is $account or one of its descendants.
     */
    private function wouldCycle(ChartOfAccount $account, int $parentId): bool
    {
        if ($parentId === $account->id) {
            return true;
        }

        $descendants = [];
        $stack = $account->children()->pluck('id')->all();
        while ($stack !== []) {
            $id = array_pop($stack);
            if (in_array($id, $descendants, true)) {
                continue;
            }
            $descendants[] = $id;
            $stack = array_merge(
                $stack,
                ChartOfAccount::query()->where('parent_account_id', $id)->pluck('id')->all(),
            );
        }

        return in_array($parentId, $descendants, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ChartOfAccount $a): array
    {
        return [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'description' => $a->description,
            'account_type' => $a->account_type?->value,
            'account_type_label' => $a->account_type?->label(),
            'account_subtype' => $a->account_subtype,
            'normal_balance' => $a->normal_balance,
            'parent_account_id' => $a->parent_account_id,
            'is_postable' => $a->is_postable,
            'is_active' => $a->isActive(),
            'active_from' => $a->active_from?->toDateString(),
            'active_to' => $a->active_to?->toDateString(),
            'journal_entries_count' => $a->journal_entries_count ?? 0,
        ];
    }
}
