<?php

namespace App\Support;

/**
 * Money precision. Amounts are stored as integer cents; conversion from a
 * user-entered dollar value happens once, at the input boundary. Centralizing
 * the rounding rule keeps it from drifting across call sites.
 *
 * Write-side only. Display (cents -> dollars) is owned by
 * App\Services\Accounting\ValueFormatter, the canonical money formatter - do
 * not add a parallel formatter here.
 */
class Money
{
    /**
     * Parse a user-entered dollar amount into integer cents.
     *
     * Intentionally takes a NON-NULL value: call sites differ in how they treat
     * an absent amount (some coalesce to 0, some persist NULL), so null handling
     * stays at the call site rather than being baked in here.
     */
    public static function toCents(int|float|string $dollars): int
    {
        return (int) round(((float) $dollars) * 100);
    }
}
