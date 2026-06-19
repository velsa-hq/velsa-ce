<?php

namespace App\Services\Payments;

use App\Support\SafeMode;
use Illuminate\Support\Str;

/**
 * Decorator that blocks live charges in safe mode (Demo/Training/UAT). When
 * SafeMode is on it never delegates to the wrapped processor, so a real merchant
 * account can't be charged from a non-prod instance whatever driver is bound;
 * returns a simulated result instead. Enforcing this at the single binding
 * chokepoint (rather than per call site) is defense-in-depth for NIST SC-*.
 * The "0000" decline convention is kept so demos can walk the declined path.
 */
final class SafeModePaymentProcessor implements PaymentProcessor
{
    public function __construct(private readonly PaymentProcessor $inner) {}

    public function charge(string $cardToken, int $amountCents, string $idempotencyKey): ChargeResult
    {
        if (! SafeMode::enabled()) {
            return $this->inner->charge($cardToken, $amountCents, $idempotencyKey);
        }

        $last4 = $this->last4($cardToken);

        if ($last4 === '0000') {
            return new ChargeResult(
                approved: false,
                transactionId: '',
                amountCents: $amountCents,
                last4: $last4,
                cardBrand: 'unknown',
                failureReason: 'Card declined (safe-mode simulation).',
            );
        }

        return new ChargeResult(
            approved: true,
            transactionId: 'sm_'.Str::uuid()->toString(),
            amountCents: $amountCents,
            last4: $last4,
            cardBrand: 'visa',
        );
    }

    public function refund(string $transactionId, int $amountCents, string $idempotencyKey): ChargeResult
    {
        if (! SafeMode::enabled()) {
            return $this->inner->refund($transactionId, $amountCents, $idempotencyKey);
        }

        return new ChargeResult(
            approved: true,
            transactionId: 'sm_refund_'.Str::uuid()->toString(),
            amountCents: $amountCents,
            last4: '0000',
            cardBrand: 'unknown',
        );
    }

    private function last4(string $cardToken): string
    {
        $digits = preg_replace('/[^0-9]/', '', $cardToken) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -4) : '0000';
    }
}
