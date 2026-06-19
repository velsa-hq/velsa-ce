<?php

namespace App\Enums;

/**
 * Exhibitor permit lifecycle; Approved/Denied/Cancelled are terminal (new request to change).
 *
 *   Pending -> Approved | Denied  (staff review)
 *   Pending -> Cancelled          (exhibitor withdraws)
 */
enum ExhibitorPermitStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Denied = 'denied';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Denied => 'Denied',
            self::Cancelled => 'Cancelled',
        };
    }
}
