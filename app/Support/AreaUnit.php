<?php

namespace App\Support;

use App\Services\SystemSettings\SystemSettings;

/**
 * Area unit display localization. Area is always stored in square feet;
 * conversion to/from the org display unit happens only at display and input.
 */
class AreaUnit
{
    /** 1 square metre = this many square feet. */
    public const SQFT_PER_SQM = 10.7639;

    public static function isMetric(): bool
    {
        return (bool) app(SystemSettings::class)->get('defaults.use_metric_units', false);
    }

    /** Display label for the current unit. */
    public static function label(): string
    {
        return self::isMetric() ? 'm²' : 'ft²';
    }

    /** Square feet per one display unit. */
    public static function sqftPerUnit(): float
    {
        return self::isMetric() ? self::SQFT_PER_SQM : 1.0;
    }

    /** Canonical square feet -> display-unit value (rounded). */
    public static function fromSqft(int|float $sqft): int
    {
        return (int) round($sqft / self::sqftPerUnit());
    }

    /** Display-unit value -> canonical square feet (rounded). */
    public static function toSqft(int|float $display): int
    {
        return (int) round($display * self::sqftPerUnit());
    }

    /** Formatted area for server-rendered output. */
    public static function format(int|float|null $sqft): string
    {
        if ($sqft === null) {
            return '-';
        }

        return number_format(self::fromSqft($sqft)).' '.self::label();
    }

    /**
     * Config payload so the front-end can convert and label areas itself.
     *
     * @return array{metric: bool, unit: string, sqft_per_unit: float}
     */
    public static function config(): array
    {
        return [
            'metric' => self::isMetric(),
            'unit' => self::label(),
            'sqft_per_unit' => self::sqftPerUnit(),
        ];
    }
}
