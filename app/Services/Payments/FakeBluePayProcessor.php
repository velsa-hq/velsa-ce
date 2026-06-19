<?php

namespace App\Services\Payments;

use Illuminate\Support\Str;

/**
 * Canned BluePay-shaped processor. Token ending "0000" simulates a decline;
 * everything else approves. Keeps transactions in memory so refund() can find them.
 */
class FakeBluePayProcessor implements PaymentProcessor
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $transactions = [];

    public function charge(string $cardToken, int $amountCents, string $idempotencyKey): ChargeResult
    {
        if (isset($this->transactions[$idempotencyKey])) {
            $t = $this->transactions[$idempotencyKey];

            return new ChargeResult(
                approved: true,
                transactionId: $t['transaction_id'],
                amountCents: $t['amount_cents'],
                last4: $t['last4'],
                cardBrand: $t['card_brand'],
            );
        }

        $digits = preg_replace('/[^0-9]/', '', $cardToken) ?? '';
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : '0000';

        if ($last4 === '0000') {
            return new ChargeResult(
                approved: false,
                transactionId: '',
                amountCents: $amountCents,
                last4: $last4,
                cardBrand: 'unknown',
                failureReason: 'Card declined by issuer (test 0000).',
            );
        }

        $transactionId = 'bp_'.Str::uuid()->toString();
        $brand = $this->guessBrand($cardToken);

        $this->transactions[$idempotencyKey] = [
            'transaction_id' => $transactionId,
            'amount_cents' => $amountCents,
            'last4' => $last4,
            'card_brand' => $brand,
            'token' => $cardToken,
        ];

        return new ChargeResult(
            approved: true,
            transactionId: $transactionId,
            amountCents: $amountCents,
            last4: $last4,
            cardBrand: $brand,
        );
    }

    public function refund(string $transactionId, int $amountCents, string $idempotencyKey): ChargeResult
    {
        $original = collect($this->transactions)->firstWhere('transaction_id', $transactionId);
        if ($original === null) {
            return new ChargeResult(
                approved: false,
                transactionId: '',
                amountCents: $amountCents,
                last4: '0000',
                cardBrand: 'unknown',
                failureReason: 'Original transaction not found.',
            );
        }

        return new ChargeResult(
            approved: true,
            transactionId: 'bp_refund_'.Str::uuid()->toString(),
            amountCents: $amountCents,
            last4: $original['last4'],
            cardBrand: $original['card_brand'],
        );
    }

    protected function guessBrand(string $token): string
    {
        return match (true) {
            str_starts_with($token, 'visa_') => 'visa',
            str_starts_with($token, 'mc_') => 'mastercard',
            str_starts_with($token, 'amex_') => 'amex',
            default => 'visa',
        };
    }
}
