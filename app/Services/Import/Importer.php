<?php

namespace App\Services\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * A single importable record "kind": describes its target fields and validates
 * + persists one already-mapped row. ImportService owns file reading, mapping,
 * transactions, error recording, and reversal.
 */
interface Importer
{
    /** Stable machine key, e.g. "clients". Used in routes + config. */
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /** Whether committing this kind should require read-only/maintenance mode. */
    public function requiresReadOnly(): bool;

    /**
     * Target fields, in display order.
     *
     * @return list<ImportField>
     */
    public function fields(): array;

    /**
     * Validate (and on commit, persist) one field-keyed row.
     *
     * @param  array<string, string|null>  $row  mapped values; unmapped fields absent
     */
    public function import(array $row, bool $dryRun): ImportRowResult;

    /**
     * Whether a created record is now referenced by data the import did NOT
     * create, so reversal must not detach or cascade into it (e.g. a booking
     * made against an imported client after the import).
     */
    public function isReferenced(Model $model): bool;
}
