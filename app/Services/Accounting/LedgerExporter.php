<?php

namespace App\Services\Accounting;

use App\Models\ExportTemplate;
use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ledger journal export abstraction. A driver claims unexported entries
 * for a period into a batch, then renders the payload via an admin-
 * configurable ExportTemplate (format, columns, masks, CSV/fixed-width).
 */
interface LedgerExporter
{
    /**
     * Claim unexported entries for the period (YYYY-MM) into a new batch,
     * using the supplied template or the configured default.
     */
    public function exportPeriod(string $period, ?int $userId = null, ?ExportTemplate $template = null): LedgerExportBatch;

    /**
     * Re-render an existing batch without re-claiming entries. Uses the
     * batch's original template so historical payloads stay reproducible.
     */
    public function renderPayload(LedgerExportBatch $batch): string;

    /**
     * @return Collection<int, JournalEntry>
     */
    public function entriesFor(LedgerExportBatch $batch): Collection;
}
