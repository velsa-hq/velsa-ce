<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\LedgerExportBatch;

/**
 * Controlled vocabulary of column.source values for the export template
 * engine. New source: declare here, handle in resolve(), list in options().
 */
final class ExportSource
{
    // Per-entry fields
    public const ACCOUNT_CODE = 'account_code';

    public const FUND_CODE = 'fund_code';

    public const DESCRIPTION = 'description';

    public const DEBIT_CENTS = 'debit_cents';

    public const CREDIT_CENTS = 'credit_cents';

    public const POSTED_ON = 'posted_on';

    public const VENUE_NAME = 'venue_name';

    public const ENTRY_ID = 'entry_id';

    public const SOURCE_TYPE = 'source_type';

    public const SOURCE_ID = 'source_id';

    // Per-batch / per-row context (computed)
    public const BATCH_ID = 'batch_id';

    public const BATCH_PERIOD = 'batch_period';

    public const BATCH_PREFIX = 'batch_prefix';

    public const LINE_NUMBER = 'line_number';

    public const DR_OR_CR = 'dr_or_cr'; // "DR" if debit_cents > 0 else "CR"

    public const ABSOLUTE_AMOUNT_CENTS = 'absolute_amount_cents';

    /**
     * @return array<int, array{value: string, label: string, group: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::ENTRY_ID, 'label' => 'Journal entry ID', 'group' => 'Entry'],
            ['value' => self::ACCOUNT_CODE, 'label' => 'Account code', 'group' => 'Entry'],
            ['value' => self::FUND_CODE, 'label' => 'Fund code', 'group' => 'Entry'],
            ['value' => self::DESCRIPTION, 'label' => 'Description', 'group' => 'Entry'],
            ['value' => self::DEBIT_CENTS, 'label' => 'Debit (cents)', 'group' => 'Entry'],
            ['value' => self::CREDIT_CENTS, 'label' => 'Credit (cents)', 'group' => 'Entry'],
            ['value' => self::POSTED_ON, 'label' => 'Posted date', 'group' => 'Entry'],
            ['value' => self::VENUE_NAME, 'label' => 'Venue name', 'group' => 'Entry'],
            ['value' => self::SOURCE_TYPE, 'label' => 'Source type (booking, etc.)', 'group' => 'Entry'],
            ['value' => self::SOURCE_ID, 'label' => 'Source ID', 'group' => 'Entry'],
            ['value' => self::BATCH_ID, 'label' => 'Batch ID', 'group' => 'Batch'],
            ['value' => self::BATCH_PERIOD, 'label' => 'Batch period (YYYY-MM)', 'group' => 'Batch'],
            ['value' => self::BATCH_PREFIX, 'label' => 'Batch prefix (PEMS-YYYY-MM-######)', 'group' => 'Batch'],
            ['value' => self::LINE_NUMBER, 'label' => 'Line number within batch', 'group' => 'Batch'],
            ['value' => self::DR_OR_CR, 'label' => '"DR" or "CR"', 'group' => 'Computed'],
            ['value' => self::ABSOLUTE_AMOUNT_CENTS, 'label' => 'Absolute amount (cents)', 'group' => 'Computed'],
        ];
    }

    /**
     * Resolve a source key against an entry + batch + row context.
     *
     * @param  array{line_number:int}  $rowContext
     */
    public static function resolve(string $source, JournalEntry $entry, LedgerExportBatch $batch, array $rowContext): mixed
    {
        return match ($source) {
            self::ACCOUNT_CODE => $entry->account_code,
            self::FUND_CODE => $entry->fund_code,
            self::DESCRIPTION => $entry->description,
            self::DEBIT_CENTS => $entry->debit_cents,
            self::CREDIT_CENTS => $entry->credit_cents,
            self::POSTED_ON => $entry->posted_on,
            self::VENUE_NAME => $entry->venue?->name,
            self::ENTRY_ID => $entry->id,
            self::SOURCE_TYPE => $entry->source_type,
            self::SOURCE_ID => $entry->source_id,
            self::BATCH_ID => $batch->id,
            self::BATCH_PERIOD => $batch->period,
            self::BATCH_PREFIX => sprintf('PEMS-%s-%06d', $batch->period, $batch->id),
            self::LINE_NUMBER => $rowContext['line_number'] ?? 0,
            self::DR_OR_CR => $entry->debit_cents > 0 ? 'DR' : 'CR',
            self::ABSOLUTE_AMOUNT_CENTS => max($entry->debit_cents, $entry->credit_cents),
            default => null,
        };
    }
}
