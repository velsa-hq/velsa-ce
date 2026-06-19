<?php

namespace App\Services\Payments;

/**
 * Provider-agnostic result of a charge attempt.
 */
final class ChargeResult
{
    public function __construct(
        public readonly bool $approved,
        public readonly string $transactionId,
        public readonly int $amountCents,
        public readonly string $last4,
        public readonly string $cardBrand,
        public readonly ?string $failureReason = null,
    ) {}
}
