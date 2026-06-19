<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

/**
 * Stripe gateway behind the PaymentProcessor interface. Talks to Stripe's REST
 * API directly over the HTTP client (no SDK). Server only handles the
 * payment-method token, never the PAN, so the CDE stays SAQ-A. The
 * Idempotency-Key header makes retries safe.
 */
class StripeProcessor implements PaymentProcessor
{
    public function __construct(
        private string $secret,
        private string $baseUrl = 'https://api.stripe.com/v1',
        private string $currency = 'usd',
    ) {}

    public function charge(string $cardToken, int $amountCents, string $idempotencyKey): ChargeResult
    {
        $response = Http::asForm()
            ->withToken($this->secret)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post("{$this->baseUrl}/payment_intents", [
                'amount' => $amountCents,
                'currency' => $this->currency,
                'payment_method' => $cardToken,
                'confirm' => 'true',
                'off_session' => 'true',
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ($body['status'] ?? null) !== 'succeeded') {
            return new ChargeResult(
                approved: false,
                transactionId: (string) ($body['id'] ?? ''),
                amountCents: $amountCents,
                last4: '',
                cardBrand: '',
                failureReason: $this->errorMessage($body),
            );
        }

        $card = $body['charges']['data'][0]['payment_method_details']['card'] ?? [];

        return new ChargeResult(
            approved: true,
            transactionId: (string) ($body['id'] ?? ''),
            amountCents: (int) ($body['amount'] ?? $amountCents),
            last4: (string) ($card['last4'] ?? ''),
            cardBrand: (string) ($card['brand'] ?? ''),
        );
    }

    public function refund(string $transactionId, int $amountCents, string $idempotencyKey): ChargeResult
    {
        $response = Http::asForm()
            ->withToken($this->secret)
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post("{$this->baseUrl}/refunds", [
                'payment_intent' => $transactionId,
                'amount' => $amountCents,
            ]);

        $body = $response->json() ?? [];

        if (! $response->successful() || ! in_array($body['status'] ?? null, ['succeeded', 'pending'], true)) {
            return new ChargeResult(
                approved: false,
                transactionId: (string) ($body['id'] ?? ''),
                amountCents: $amountCents,
                last4: '',
                cardBrand: '',
                failureReason: $this->errorMessage($body),
            );
        }

        return new ChargeResult(
            approved: true,
            transactionId: (string) ($body['id'] ?? ''),
            amountCents: (int) ($body['amount'] ?? $amountCents),
            last4: '',
            cardBrand: '',
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body): string
    {
        if (is_array($body['error'] ?? null)) {
            return (string) ($body['error']['message'] ?? 'Payment failed.');
        }

        return 'Payment failed.';
    }
}
