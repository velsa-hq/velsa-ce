<?php

namespace App\Enums;

/**
 * Contract lifecycle.
 *
 *   draft -> sent -> viewed -> partially_signed -> signed
 *   draft -> sent -> viewed -> declined
 *   draft -> sent -> expired (auto via cron)
 *   * -> voided (admin force)
 */
enum ContractStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case PartiallySigned = 'partially_signed';
    case Signed = 'signed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Voided = 'voided';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Signed, self::Declined, self::Expired, self::Voided => true,
            default => false,
        };
    }

    public function isImmutable(): bool
    {
        return $this === self::Signed;
    }

    public function isInFlight(): bool
    {
        return match ($this) {
            self::Sent, self::Viewed, self::PartiallySigned => true,
            default => false,
        };
    }

    /** Only in-flight envelopes can be voided; drafts are deleted, signed/terminal are final. */
    public function isVoidable(): bool
    {
        return $this->isInFlight();
    }

    /** Never delete an in-flight envelope (void it first) or a signed record. */
    public function isDeletable(): bool
    {
        return match ($this) {
            self::Draft, self::Declined, self::Expired, self::Voided => true,
            default => false,
        };
    }
}
