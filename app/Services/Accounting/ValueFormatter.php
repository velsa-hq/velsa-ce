<?php

namespace App\Services\Accounting;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * Format-mask interpreter used by the export template renderer. Masks
 * are admin-supplied strings like 'date:Y-m-d', 'money:dot',
 * 'pad-zero:6'. Unknown masks throw - better to fail loudly during
 * configuration than to silently produce a broken export.
 */
final class ValueFormatter
{
    /**
     * Apply a single format mask. Pass null to leave the value alone.
     */
    public static function apply(mixed $value, ?string $mask): string
    {
        if ($mask === null || $mask === '') {
            return self::stringify($value);
        }

        // pipe for chained masks: 'upper|truncate:10'
        if (str_contains($mask, '|')) {
            foreach (explode('|', $mask) as $stage) {
                $value = self::apply($value, trim($stage));
            }

            return self::stringify($value);
        }

        [$name, $arg] = self::split($mask);

        return match ($name) {
            'raw' => self::stringify($value),
            'upper' => mb_strtoupper(self::stringify($value)),
            'lower' => mb_strtolower(self::stringify($value)),
            'trim' => trim(self::stringify($value)),
            'truncate' => mb_substr(self::stringify($value), 0, self::intArg($arg, 'truncate')),
            'pad-zero' => str_pad(self::stringify($value), self::intArg($arg, 'pad-zero'), '0', STR_PAD_LEFT),
            'pad' => str_pad(self::stringify($value), self::intArg($arg, 'pad'), ' ', STR_PAD_LEFT),
            'date' => self::formatDate($value, $arg ?? 'Y-m-d'),
            'money:dot' => self::formatMoneyDot($value),
            'money:int' => self::formatMoneyInt($value),
            'money:dollars' => self::formatMoneyDollars($value),
            'money:signed' => self::formatMoneySigned($value),
            'coalesce' => self::stringify($value) === '' ? ($arg ?? '') : self::stringify($value),
            default => throw new InvalidArgumentException("Unknown format mask: {$mask}"),
        };
    }

    /**
     * Escape a CSV cell - quotes the field if it contains the delimiter,
     * quote char, or any newline. Doubles embedded quote chars.
     */
    public static function csvEscape(string $value, string $delimiter, string $quoteChar): string
    {
        if ($delimiter === '' || $quoteChar === '') {
            return $value;
        }

        $needsQuoting = str_contains($value, $delimiter)
            || str_contains($value, $quoteChar)
            || str_contains($value, "\n")
            || str_contains($value, "\r");

        if (! $needsQuoting) {
            return $value;
        }

        $escaped = str_replace($quoteChar, $quoteChar.$quoteChar, $value);

        return $quoteChar.$escaped.$quoteChar;
    }

    /**
     * Pad / truncate to an exact width for fixed-width formats. Aligns
     * left or right, fills with the supplied pad char.
     */
    public static function fixedWidth(string $value, int $width, string $align = 'left', string $padChar = ' '): string
    {
        if ($width <= 0) {
            return $value;
        }
        if ($padChar === '') {
            $padChar = ' ';
        }

        $current = mb_strlen($value);

        if ($current > $width) {
            return mb_substr($value, 0, $width);
        }

        return $align === 'right'
            ? str_pad($value, $width, $padChar, STR_PAD_LEFT)
            : str_pad($value, $width, $padChar, STR_PAD_RIGHT);
    }

    /**
     * Display money helpers (with thousands separators):
     *
     *   dollars(1500000)     => "15,000.00"
     *   usd(1500000)         => "$15,000.00"
     *   usdRounded(1500000)  => "$15,000"
     *
     * For the no-thousands export form use the 'money:dot' mask.
     */
    public static function dollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    public static function usd(int $cents): string
    {
        return '$'.self::dollars($cents);
    }

    public static function usdRounded(int $cents): string
    {
        return '$'.number_format($cents / 100, 0);
    }

    /**
     * @return array{0:string, 1:?string}
     */
    private static function split(string $mask): array
    {
        // compound names contain a colon, so match the full mask before
        // falling back to "name:arg"
        $compounds = ['money:dot', 'money:int', 'money:dollars', 'money:signed'];
        foreach ($compounds as $c) {
            if ($mask === $c) {
                return [$c, null];
            }
        }

        if (! str_contains($mask, ':')) {
            return [$mask, null];
        }

        [$name, $arg] = explode(':', $mask, 2);

        return [$name, $arg];
    }

    private static function intArg(?string $arg, string $maskName): int
    {
        if ($arg === null || ! ctype_digit($arg)) {
            throw new InvalidArgumentException("Mask '{$maskName}' requires an integer argument.");
        }

        return (int) $arg;
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private static function formatDate(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return (string) $value;
        }

        return date($format, $timestamp);
    }

    private static function formatMoneyDot(mixed $cents): string
    {
        $intCents = (int) $cents;

        return number_format($intCents / 100, 2, '.', '');
    }

    private static function formatMoneyInt(mixed $cents): string
    {
        return (string) (int) $cents;
    }

    private static function formatMoneyDollars(mixed $cents): string
    {
        $intCents = (int) $cents;

        return (string) (int) round($intCents / 100);
    }

    private static function formatMoneySigned(mixed $cents): string
    {
        $intCents = (int) $cents;
        $sign = $intCents < 0 ? '-' : '';

        return $sign.number_format(abs($intCents) / 100, 2, '.', '');
    }
}
