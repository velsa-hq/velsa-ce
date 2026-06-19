<?php

namespace App\Enums;

/** Output formats for the journal export engine. */
enum ExportFormat: string
{
    case Csv = 'csv';
    case FixedWidth = 'fixed_width';

    public function label(): string
    {
        return match ($this) {
            self::Csv => 'CSV (delimited)',
            self::FixedWidth => 'Fixed-width',
        };
    }
}
