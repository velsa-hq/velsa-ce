<?php

namespace App\Services\Accounting;

use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Services\AuditLogger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Manual general-ledger writes: posting a balanced journal entry, reversing a
 * posted group, and voiding an export batch. Owns the double-entry invariant
 * and the posting transactions so they're unit-testable and reusable; the
 * controller keeps HTTP concerns (request validation, abort guards, toasts).
 *
 * Precondition failures throw JournalEntryException (framework-neutral,
 * carrying the form field) for the caller to map to a keyed validation error.
 */
class JournalEntryService
{
    public function __construct(protected AuditLogger $auditLogger) {}

    /**
     * Post a manually-entered journal entry. Validates the double-entry
     * invariant (each line a debit XOR a credit; debits == credits; total > 0)
     * and that the target fiscal year is open, then posts every leg under one
     * entry_group in a single transaction.
     *
     * @param  array<int, array{account_code: string, fund_code?: ?string, debit_cents?: ?int, credit_cents?: ?int}>  $lines
     *
     * @throws JournalEntryException
     */
    public function postManualEntry(array $lines, string $description, ?int $venueId, ?string $postedOn, ?int $userId): void
    {
        $postedOn ??= now()->toDateString();

        $year = FiscalYear::forDate(new DateTimeImmutable($postedOn));
        if ($year !== null && $year->is_closed) {
            throw new JournalEntryException('posted_on', "Fiscal year '{$year->label}' is closed; entries can't be posted into it.");
        }

        // each line is a debit XOR a credit; the entry must balance and be
        // nonzero - the invariant the ledger depends on
        $debits = 0;
        $credits = 0;
        foreach ($lines as $i => $line) {
            $debit = (int) ($line['debit_cents'] ?? 0);
            $credit = (int) ($line['credit_cents'] ?? 0);
            if (($debit > 0) === ($credit > 0)) {
                throw new JournalEntryException("lines.$i", 'Each line must be either a debit or a credit, not both or neither.');
            }
            $debits += $debit;
            $credits += $credit;
        }
        if ($debits !== $credits || $debits === 0) {
            throw new JournalEntryException('lines', 'Debits must equal credits and total more than zero.');
        }

        $group = (string) Str::uuid();

        try {
            DB::transaction(function () use ($lines, $description, $venueId, $postedOn, $userId, $group): void {
                foreach ($lines as $line) {
                    JournalEntry::post([
                        'venue_id' => $venueId,
                        'entry_group' => $group,
                        'account_code' => $line['account_code'],
                        'fund_code' => $line['fund_code'] ?? null,
                        'description' => $description,
                        'debit_cents' => (int) ($line['debit_cents'] ?? 0),
                        'credit_cents' => (int) ($line['credit_cents'] ?? 0),
                        'posted_on' => $postedOn,
                        'posted_by_user_id' => $userId,
                    ]);
                }
            });
        } catch (RuntimeException $e) {
            // model-level guards (non-postable / inactive account or fund)
            throw new JournalEntryException('lines', $e->getMessage());
        }
    }

    /**
     * Reverse a manually-posted entry group by posting the mirror of every leg
     * (debit<->credit), linked via reversed_entry_id. Append-only: ledgers
     * correct by reversal, never by editing. Returns the number of legs
     * reversed, or null if the group was already reversed (a soft no-op the
     * caller surfaces as a notice rather than an error).
     */
    public function reverse(JournalEntry $entry, ?int $userId = null): ?int
    {
        $legs = JournalEntry::query()->where('entry_group', $entry->entry_group)->get();

        $alreadyReversed = JournalEntry::query()
            ->whereIn('reversed_entry_id', $legs->pluck('id'))
            ->exists();
        if ($alreadyReversed) {
            return null;
        }

        $group = (string) Str::uuid();

        DB::transaction(function () use ($legs, $group, $userId): void {
            foreach ($legs as $leg) {
                JournalEntry::post([
                    'venue_id' => $leg->venue_id,
                    'entry_group' => $group,
                    'reversed_entry_id' => $leg->id,
                    'account_code' => $leg->account_code,
                    'fund_code' => $leg->fund_code,
                    'description' => 'Reversal: '.$leg->description,
                    'debit_cents' => $leg->credit_cents,
                    'credit_cents' => $leg->debit_cents,
                    'posted_on' => now()->toDateString(),
                    'posted_by_user_id' => $userId,
                ]);
            }
        });

        return $legs->count();
    }

    /**
     * Void a ledger export batch: detach its entries back into the pending
     * queue, stamp the batch voided, and audit. A batch-lifecycle operation,
     * not a double-entry GL change (it posts no legs).
     */
    public function voidBatch(LedgerExportBatch $batch, string $reason, ?int $userId): void
    {
        DB::transaction(function () use ($batch, $reason, $userId): void {
            $detached = JournalEntry::query()
                ->where('export_batch_id', $batch->id)
                ->update(['export_batch_id' => null]);

            $batch->update([
                'status' => 'voided',
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by_user_id' => $userId,
            ]);

            $this->auditLogger->record(
                eventType: 'ledger.batch_voided',
                subject: $batch->fresh(),
                payload: [
                    'period' => $batch->period,
                    'entries_detached' => $detached,
                    'reason' => $reason,
                ],
            );
        });
    }
}
