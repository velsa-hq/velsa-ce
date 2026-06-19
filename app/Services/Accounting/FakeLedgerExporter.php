<?php

namespace App\Services\Accounting;

use App\Models\ExportTemplate;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stub ledger exporter. Atomically claims unexported journal entries for
 * a period (YYYY-MM) into a new LedgerExportBatch and renders the payload
 * via the supplied (or default) ExportTemplate. A real GL driver swaps in
 * later behind the same template engine.
 */
class FakeLedgerExporter implements LedgerExporter
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected ExportTemplateRenderer $renderer,
    ) {}

    public function exportPeriod(string $period, ?int $userId = null, ?ExportTemplate $template = null): LedgerExportBatch
    {
        $template ??= ExportTemplate::resolveDefault();
        if ($template === null) {
            throw new RuntimeException('No export template configured. Seed at least one ExportTemplate before exporting.');
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        return DB::transaction(function () use ($period, $userId, $start, $end, $template) {
            $batch = LedgerExportBatch::query()->create([
                'period' => $period,
                'status' => 'pending',
                'entry_count' => 0,
                'debit_total_cents' => 0,
                'credit_total_cents' => 0,
                'export_template_id' => $template->id,
                'created_by_user_id' => $userId,
            ]);

            $entries = JournalEntry::query()
                ->unexported()
                ->whereDate('posted_on', '>=', $start->toDateString())
                ->whereDate('posted_on', '<=', $end->toDateString())
                ->orderBy('posted_on')
                ->orderBy('id')
                ->get();

            if ($entries->isEmpty()) {
                $batch->update(['status' => 'empty']);

                return $batch->fresh();
            }

            $batchId = $batch->id;
            $debits = 0;
            $credits = 0;

            foreach ($entries as $entry) {
                $entry->update(['export_batch_id' => $batchId]);
                $debits += $entry->debit_cents;
                $credits += $entry->credit_cents;
            }

            $batch->update([
                'entry_count' => $entries->count(),
                'debit_total_cents' => $debits,
                'credit_total_cents' => $credits,
                'status' => $debits === $credits ? 'ready' : 'unbalanced',
                'file_s3_key' => sprintf('ledger-exports/%s/%d.%s', $period, $batch->id, $template->file_extension),
            ]);

            $this->auditLogger->record(
                eventType: 'ledger.export',
                subject: $batch->fresh(),
                payload: [
                    'period' => $period,
                    'entry_count' => $entries->count(),
                    'debit_cents' => $debits,
                    'credit_cents' => $credits,
                    'balanced' => $debits === $credits,
                    'template_slug' => $template->slug,
                ],
            );

            return $batch->fresh(['entries', 'template']);
        });
    }

    public function renderPayload(LedgerExportBatch $batch): string
    {
        $template = $batch->template ?? ExportTemplate::resolveDefault();
        if ($template === null) {
            throw new RuntimeException('Batch has no template and no default exists.');
        }

        return $this->renderer->render($template, $batch, $this->entriesFor($batch));
    }

    public function entriesFor(LedgerExportBatch $batch): Collection
    {
        return JournalEntry::query()
            ->where('export_batch_id', $batch->id)
            ->with('venue:id,name')
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get();
    }
}
