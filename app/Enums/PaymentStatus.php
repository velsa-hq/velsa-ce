<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Voided = 'voided';

    public function isSettled(): bool
    {
        return $this === self::Captured;
    }
}
