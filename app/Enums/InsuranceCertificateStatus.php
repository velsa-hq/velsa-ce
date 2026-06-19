<?php

namespace App\Enums;

/**
 * Review/lifecycle state of an insurance certificate (COI):
 *
 *   Pending  -> Approved | Rejected   (staff review)
 *   Approved -> Expired                (nightly job, once past expiry)
 *
 * Rejected is terminal for that submission; the holder resubmits a new one.
 */
enum InsuranceCertificateStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }
}
