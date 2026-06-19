<?php

use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\StripeProcessor;
use Illuminate\Support\Facades\Http;

function stripe(): StripeProcessor
{
    return new StripeProcessor('sk_test_123', 'https://api.stripe.com/v1', 'usd');
}

it('charges a tokenized card via Stripe and maps the result', function () {
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response([
            'id' => 'pi_abc',
            'status' => 'succeeded',
            'amount' => 5000,
            'charges' => ['data' => [['payment_method_details' => ['card' => ['last4' => '4242', 'brand' => 'visa']]]]],
        ]),
    ]);

    $result = stripe()->charge('pm_card_visa', 5000, 'idem-1');

    expect($result->approved)->toBeTrue()
        ->and($result->transactionId)->toBe('pi_abc')
        ->and($result->amountCents)->toBe(5000)
        ->and($result->last4)->toBe('4242')
        ->and($result->cardBrand)->toBe('visa');

    Http::assertSent(fn ($req) => str_contains($req->url(), '/payment_intents')
        && $req->hasHeader('Idempotency-Key', 'idem-1')
        && $req->hasHeader('Authorization', 'Bearer sk_test_123')
        && $req['payment_method'] === 'pm_card_visa');
});

it('returns a declined result with the Stripe error message', function () {
    Http::fake([
        'api.stripe.com/*' => Http::response(['error' => ['message' => 'Your card was declined.']], 402),
    ]);

    $result = stripe()->charge('pm_card_chargeDeclined', 5000, 'idem-2');

    expect($result->approved)->toBeFalse()
        ->and($result->failureReason)->toBe('Your card was declined.');
});

it('refunds a payment intent', function () {
    Http::fake([
        'api.stripe.com/v1/refunds' => Http::response(['id' => 're_abc', 'status' => 'succeeded', 'amount' => 5000]),
    ]);

    $result = stripe()->refund('pi_abc', 5000, 'idem-3');

    expect($result->approved)->toBeTrue()
        ->and($result->transactionId)->toBe('re_abc');
});

it('binds Stripe as the active processor by config', function () {
    config([
        'payments.processor' => 'stripe',
        'payments.stripe.secret' => 'sk_test_123',
        'payments.stripe.base_url' => 'https://api.stripe.com/v1',
        'payments.stripe.currency' => 'usd',
    ]);
    $this->app->forgetInstance(PaymentProcessor::class);

    Http::fake([
        'api.stripe.com/*' => Http::response([
            'id' => 'pi_plugin',
            'status' => 'succeeded',
            'amount' => 2500,
            'charges' => ['data' => [['payment_method_details' => ['card' => ['last4' => '0005', 'brand' => 'amex']]]]],
        ]),
    ]);

    $result = app(PaymentProcessor::class)->charge('pm_card', 2500, 'idem-4');

    expect($result->approved)->toBeTrue()
        ->and($result->transactionId)->toBe('pi_plugin');
    Http::assertSent(fn ($req) => str_contains($req->url(), 'api.stripe.com'));
});
