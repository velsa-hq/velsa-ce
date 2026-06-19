<?php

namespace App\Services\Payments;

/**
 * Card-tokenized payment processor abstraction.
 *
 * The card is tokenized client-side via a hosted iframe; the server only ever
 * sees the token + last4 + brand, never the PAN. That keeps the cardholder-data
 * environment in PCI-DSS SAQ-A scope.
 */
interface PaymentProcessor
{
    /**
     * Charge a tokenized card. $idempotencyKey guards against double-charge on retry.
     */
    public function charge(string $cardToken, int $amountCents, string $idempotencyKey): ChargeResult;

    public function refund(string $transactionId, int $amountCents, string $idempotencyKey): ChargeResult;
}
