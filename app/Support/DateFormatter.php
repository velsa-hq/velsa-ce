<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Shared date/time display formats. Null in, null out.
 */
final class DateFormatter
{
    /** datetime-local <input> value, e.g. "2026-06-01T09:00". */
    public static function editDateTime(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d\TH:i');
    }

    /** Full weekday + date, e.g. "Monday, Jun 1, 2026". */
    public static function dayLabel(?DateTimeInterface $date): ?string
    {
        return $date?->format('l, M j, Y');
    }

    /** Time of day, e.g. "9:00 AM". */
    public static function timeOnly(?DateTimeInterface $date): ?string
    {
        return $date?->format('g:i A');
    }

    /** Date + time with a middot, e.g. "Jun 1, 2026 · 9:00 AM". */
    public static function dateTime(?DateTimeInterface $date): ?string
    {
        return $date?->format('M j, Y · g:i A');
    }

    /** Short weekday + date + time, e.g. "Mon, Jun 1, 2026 · 9:00 AM". */
    public static function dateTimeWithDay(?DateTimeInterface $date): ?string
    {
        return $date?->format('D, M j, Y · g:i A');
    }

    /** Report-header timestamp (no middot), e.g. "Jun 1, 2026 9:00 AM". */
    public static function reportStamp(?DateTimeInterface $date): ?string
    {
        return $date?->format('M j, Y g:i A');
    }

    /** Filename-safe stamp, e.g. "2026-06-01_090000". Defaults to now(). */
    public static function fileStamp(?DateTimeInterface $date = null): string
    {
        return ($date ?? now())->format('Y-m-d_His');
    }
}
