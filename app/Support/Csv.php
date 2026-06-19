<?php

namespace App\Support;

/**
 * CSV output helpers. Neutralizes spreadsheet formula injection (CWE-1236):
 * a cell starting with a trigger char is prefixed with a quote so it renders as text.
 */
final class Csv
{
    /** Leading chars a spreadsheet treats as a formula. */
    private const TRIGGERS = "=+-@\t\r";

    /** Neutralize one cell; non-strings pass through. */
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (str_contains(self::TRIGGERS, $value[0])) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Neutralize every cell in a row, preserving keys.
     *
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }
}
