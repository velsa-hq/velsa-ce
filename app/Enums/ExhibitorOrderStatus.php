<?php

namespace App\Enums;

enum ExhibitorOrderStatus: string
{
    case Cart = 'cart';
    case Pending = 'pending';
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function blocksEdit(): bool
    {
        return $this === self::Paid || $this === self::Refunded;
    }
}
