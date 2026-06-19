<?php

namespace App\Enums;

/** Invoice lifecycle. Set only through InvoiceService so transitions stay auditable. */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartialPaid = 'partial_paid';
    case Paid = 'paid';
    case PastDue = 'past_due';
    case Void = 'void';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartialPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::PastDue => 'Past due',
            self::Void => 'Void',
            self::WrittenOff => 'Written off',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::PartialPaid, self::PastDue], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::WrittenOff], true);
    }
}
