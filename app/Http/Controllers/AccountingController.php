<?php

namespace App\Http\Controllers;

use App\Concerns\ReasonValidationRules;
use App\Models\ChartOfAccount;
use App\Models\ExportTemplate;
use App\Models\Fund;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Models\Venue;
use App\Services\Accounting\BatchDeliveryService;
use App\Services\Accounting\JournalEntryException;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\LedgerExporter;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    use ReasonValidationRules;

    public function journal(Request $request): Response
    {
        $venueId = $request->integer('venue_id') ?: null;
        $unexported = (bool) $request->boolean('unexported');

        $entries = JournalEntry::query()
            ->with(['venue:id,name,slug', 'exportBatch:id,period,status'])
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($unexported, fn ($q) => $q->unexported())
            ->orderByDesc('posted_on')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // which of this page's legs are already reversed (a reversing leg
        // points back at them), so the UI can disable a second reverse
        $pageLegIds = $entries->getCollection()->pluck('id')->all();
        $reversedLegIds = JournalEntry::query()
            ->whereIn('reversed_entry_id', $pageLegIds)
            ->pluck('reversed_entry_id')
            ->all();

        $rows = $entries->getCollection()->map(fn (JournalEntry $e) => [
            'id' => $e->id,
            'posted_on' => $e->posted_on?->toDateString(),
            'account_code' => $e->account_code,
            'fund_code' => $e->fund_code,
            'description' => $e->description,
            'debit_cents' => $e->debit_cents,
            'credit_cents' => $e->credit_cents,
            'venue_name' => $e->venue?->name,
            'is_manual' => $e->entry_group !== null,
            'is_reversal' => $e->reversed_entry_id !== null,
            'is_reversed' => in_array($e->id, $reversedLegIds, true),
            'export_batch' => $e->exportBatch ? [
                'id' => $e->exportBatch->id,
                'period' => $e->exportBatch->period,
                'status' => $e->exportBatch->status,
            ] : null,
        ]);

        $summary = JournalEntry::query()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->when($unexported, fn ($q) => $q->unexported())
            ->selectRaw('sum(debit_cents) as debits, sum(credit_cents) as credits, count(*) as count')
            ->first();

        $batches = LedgerExportBatch::query()
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (LedgerExportBatch $b) => [
                'id' => $b->id,
                'period' => $b->period,
                'status' => $b->status,
                'entry_count' => $b->entry_count,
                'debit_total_cents' => $b->debit_total_cents,
                'credit_total_cents' => $b->credit_total_cents,
                'balanced' => $b->isBalanced(),
                'sent_at' => $b->sent_at?->toIso8601String(),
                'acknowledged_at' => $b->acknowledged_at?->toIso8601String(),
                'voided_at' => $b->voided_at?->toIso8601String(),
                'delivery_detail' => $b->delivery_detail,
            ]);

        return Inertia::render('accounting/index', [
            'entries' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'last_page' => $entries->lastPage(),
                    'total' => $entries->total(),
                ],
                'links' => [
                    'prev' => $entries->previousPageUrl(),
                    'next' => $entries->nextPageUrl(),
                ],
            ],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'accounts' => ChartOfAccount::query()
                ->postable()
                ->active()
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (ChartOfAccount $a) => ['code' => $a->code, 'name' => $a->name]),
            'funds' => Fund::query()
                ->active()
                ->orderBy('code')
                ->get(['code', 'name'])
                ->map(fn (Fund $f) => ['code' => $f->code, 'name' => $f->name]),
            'filters' => ['venue_id' => $venueId, 'unexported' => $unexported],
            'summary' => [
                'debits_cents' => (int) ($summary->debits ?? 0),
                'credits_cents' => (int) ($summary->credits ?? 0),
                'count' => (int) ($summary->count ?? 0),
            ],
            'batches' => $batches,
            'export_templates' => ExportTemplate::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name', 'is_default'])
                ->map(fn (ExportTemplate $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'is_default' => (bool) $t->is_default,
                ]),
            'current_period' => now()->format('Y-m'),
            'can_post' => (bool) $request->user()?->hasVenuePermission('accounting.post_journal'),
            'can_export' => (bool) $request->user()?->hasVenuePermission('accounting.export_ledger'),
        ]);
    }

    /**
     * Post a manual journal entry: N balanced legs sharing one group, so the
     * whole entry can be reversed as a unit. Gated on accounting.post_journal;
     * entries flow into the next export batch like any system entry.
     */
    public function storeEntry(Request $request, JournalEntryService $journal): RedirectResponse
    {
        $data = $request->validate([
            'posted_on' => ['nullable', 'date'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'exists:chart_of_accounts,code'],
            'lines.*.fund_code' => ['nullable', 'string', 'exists:funds,code'],
            'lines.*.debit_cents' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $journal->postManualEntry(
                $data['lines'],
                $data['description'],
                $data['venue_id'] ?? null,
                $data['posted_on'] ?? null,
                $request->user()?->id,
            );
        } catch (JournalEntryException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Journal entry posted.']);
    }

    /**
     * Reverse a manually-posted entry by posting the mirror of every leg
     * (debit<->credit), linked via reversed_entry_id. Ledgers correct by
     * reversal, never by editing (the model is append-only). System entries
     * reverse through their own flows.
     */
    public function reverseEntry(Request $request, JournalEntry $journalEntry, JournalEntryService $journal): RedirectResponse
    {
        abort_if($journalEntry->entry_group === null, 422, 'Only manually-posted entries can be reversed here.');

        if ($journal->reverse($journalEntry, $request->user()?->id) === null) {
            return back()->with('toast', ['type' => 'error', 'message' => 'This entry has already been reversed.']);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Entry reversed.']);
    }

    /**
     * Account ledger drill-down: every leg posted to one account,
     * chronological, with a running balance carried from an opening balance
     * (net of entries before the window).
     */
    public function accountLedger(Request $request, ChartOfAccount $chartOfAccount): Response
    {
        $venueId = $request->integer('venue_id') ?: null;
        $from = $request->date('from');
        $to = $request->date('to');

        $scoped = fn () => $chartOfAccount->journalEntries()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v));

        // running balance is net debits - credits: positive = a debit (Dr)
        // balance, negative = credit (Cr), correct for every account type.
        // opening = net before the window
        $opening = $from
            ? (int) (clone $scoped())->whereDate('posted_on', '<', $from)
                ->selectRaw('coalesce(sum(debit_cents - credit_cents), 0) as bal')->value('bal')
            : 0;

        $entries = $scoped()
            ->when($from, fn ($q, $v) => $q->whereDate('posted_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('posted_on', '<=', $v))
            ->with('venue:id,name')
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get();

        $running = $opening;
        $rows = $entries->map(function (JournalEntry $e) use (&$running) {
            $running += $e->debit_cents - $e->credit_cents;

            return [
                'id' => $e->id,
                'posted_on' => $e->posted_on?->toDateString(),
                'description' => $e->description,
                'debit_cents' => $e->debit_cents,
                'credit_cents' => $e->credit_cents,
                'running_cents' => $running,
                'venue_name' => $e->venue?->name,
                'is_manual' => $e->entry_group !== null,
                'is_reversal' => $e->reversed_entry_id !== null,
            ];
        });

        return Inertia::render('accounting/account-ledger', [
            'account' => [
                'code' => $chartOfAccount->code,
                'name' => $chartOfAccount->name,
                'type_label' => $chartOfAccount->account_type?->label(),
                'normal_balance' => $chartOfAccount->account_type?->normalBalance(),
            ],
            'opening_cents' => $opening,
            'closing_cents' => $running,
            'entries' => $rows,
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'venue_id' => $venueId,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    /**
     * Trial balance: every account with activity as of a date, net shown in
     * its debit or credit column. A balanced ledger has equal column totals.
     */
    public function trialBalance(Request $request): Response
    {
        $asOf = $request->date('as_of') ?? now();
        $venueId = $request->integer('venue_id') ?: null;

        $totals = JournalEntry::query()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            ->whereDate('posted_on', '<=', $asOf)
            ->selectRaw('account_code, sum(debit_cents) as debits, sum(credit_cents) as credits')
            ->groupBy('account_code')
            ->toBase()
            ->get();

        $accounts = ChartOfAccount::query()->get()->keyBy('code');

        $rows = $totals
            ->map(function (object $t) use ($accounts) {
                $net = (int) $t->debits - (int) $t->credits;
                $account = $accounts->get($t->account_code);

                return [
                    'account_code' => $t->account_code,
                    'name' => $account?->name,
                    'type_label' => $account?->account_type?->label(),
                    'debit_balance_cents' => $net > 0 ? $net : 0,
                    'credit_balance_cents' => $net < 0 ? -$net : 0,
                ];
            })
            ->sortBy('account_code')
            ->values();

        $debitTotal = (int) $rows->sum('debit_balance_cents');
        $creditTotal = (int) $rows->sum('credit_balance_cents');

        return Inertia::render('accounting/trial-balance', [
            'rows' => $rows,
            'debit_total_cents' => $debitTotal,
            'credit_total_cents' => $creditTotal,
            'balanced' => $debitTotal === $creditTotal,
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => [
                'venue_id' => $venueId,
                'as_of' => $asOf->toDateString(),
            ],
        ]);
    }

    public function export(Request $request, LedgerExporter $exporter, BatchDeliveryService $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'export_template_id' => ['nullable', 'integer', 'exists:export_templates,id'],
        ]);

        $period = $validated['period'] ?? now()->format('Y-m');
        $template = isset($validated['export_template_id'])
            ? ExportTemplate::find($validated['export_template_id'])
            : null;

        $batch = $exporter->exportPeriod($period, $request->user()?->id, $template);

        // hand a freshly-rendered balanced batch to the configured transport;
        // empty/unbalanced batches are left for review rather than auto-sent
        if ($batch->status === 'ready') {
            $batch = $delivery->deliver($batch);
        }

        return back()->with('status', "GL export {$batch->period} created ({$batch->entry_count} entries, status {$batch->status}).");
    }

    public function downloadBatch(LedgerExportBatch $batch, LedgerExporter $exporter): StreamedResponse
    {
        $payload = $exporter->renderPayload($batch);

        return response()->streamDownload(
            fn () => print ($payload),
            "ledger-{$batch->period}-{$batch->id}.csv",
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * Detail view for a single export batch: its metadata, the template
     * it rendered through, and every journal entry it claimed.
     */
    public function showBatch(Request $request, LedgerExportBatch $batch): Response
    {
        $batch->load(['template:id,name,slug', 'creator:id,name', 'voidedBy:id,name']);

        $entries = JournalEntry::query()
            ->where('export_batch_id', $batch->id)
            ->with('venue:id,name')
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get()
            ->map(fn (JournalEntry $e) => [
                'id' => $e->id,
                'posted_on' => $e->posted_on?->toDateString(),
                'account_code' => $e->account_code,
                'fund_code' => $e->fund_code,
                'description' => $e->description,
                'debit_cents' => $e->debit_cents,
                'credit_cents' => $e->credit_cents,
                'venue_name' => $e->venue?->name,
            ]);

        return Inertia::render('accounting/batch-detail', [
            'batch' => [
                'id' => $batch->id,
                'period' => $batch->period,
                'status' => $batch->status,
                'entry_count' => $batch->entry_count,
                'debit_total_cents' => $batch->debit_total_cents,
                'credit_total_cents' => $batch->credit_total_cents,
                'balanced' => $batch->isBalanced(),
                'template_name' => $batch->template?->name,
                'created_by' => $batch->creator?->name,
                'sent_at' => $batch->sent_at?->toIso8601String(),
                'acknowledged_at' => $batch->acknowledged_at?->toIso8601String(),
                'voided_at' => $batch->voided_at?->toIso8601String(),
                'void_reason' => $batch->void_reason,
                'voided_by' => $batch->voidedBy?->name,
                'delivery_transport' => $batch->delivery_transport,
                'delivery_detail' => $batch->delivery_detail,
                'failure_reason' => $batch->failure_reason,
            ],
            'entries' => $entries,
            'can_export' => (bool) $request->user()?->hasVenuePermission('accounting.export_ledger'),
        ]);
    }

    /**
     * Void an export batch: detach its entries (export_batch_id back to null
     * so they re-enter the pending queue) and stamp the batch voided. The row
     * is kept for the audit trail; only the export claim is released, not the
     * postings.
     */
    public function voidBatch(Request $request, LedgerExportBatch $batch, JournalEntryService $journal): RedirectResponse
    {
        abort_if($batch->isVoided(), 422, 'This batch has already been voided.');

        $validated = $request->validate([
            'reason' => $this->reasonRule(true),
        ]);

        $journal->voidBatch($batch, $validated['reason'], $request->user()?->id);

        return redirect()
            ->route('accounting.journal')
            ->with('status', "Export batch {$batch->period} voided - its entries are back in the pending queue.");
    }

    /**
     * Mark a batch acknowledged - the GL system (or its operators) have
     * confirmed receipt. Closes the lifecycle: ready / sent -> acknowledged.
     */
    public function acknowledgeBatch(Request $request, LedgerExportBatch $batch, AuditLogger $auditLogger): RedirectResponse
    {
        abort_if($batch->isVoided(), 422, 'A voided batch cannot be acknowledged.');
        abort_unless(in_array($batch->status, ['ready', 'sent'], true), 422, 'Only a ready or sent batch can be acknowledged.');

        $batch->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        $auditLogger->record(
            eventType: 'ledger.batch_acknowledged',
            subject: $batch->fresh(),
            payload: ['period' => $batch->period],
        );

        return back()->with('status', "Export batch {$batch->period} marked acknowledged.");
    }

    /** Re-attempt delivery of a batch through the configured transport. */
    public function resendBatch(LedgerExportBatch $batch, BatchDeliveryService $delivery): RedirectResponse
    {
        abort_if($batch->isVoided(), 422, 'A voided batch cannot be re-sent.');

        $batch = $delivery->deliver($batch);

        return back()->with('status', "Export batch {$batch->period} delivery re-attempted (status {$batch->status}).");
    }
}
