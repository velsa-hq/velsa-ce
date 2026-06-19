<?php

use App\Services\Payments\FakeBluePayProcessor;
use App\Services\Payments\PaymentProcessor;
use App\Services\Payments\SafeModePaymentProcessor;

it('binds the PaymentProcessor behind the safe-mode decorator', function () {
    expect(app(PaymentProcessor::class))->toBeInstanceOf(SafeModePaymentProcessor::class);
});

it('approves a charge against a non-0000 token', function () {
    $result = (new FakeBluePayProcessor)->charge('visa_4242', 100_00, 'idem-1');

    expect($result->approved)->toBeTrue()
        ->and($result->transactionId)->toStartWith('bp_')
        ->and($result->amountCents)->toBe(100_00)
        ->and($result->last4)->toBe('4242')
        ->and($result->cardBrand)->toBe('visa');
});

it('declines a charge against a 0000 last4 token', function () {
    $result = (new FakeBluePayProcessor)->charge('test_0000', 100_00, 'idem-2');

    expect($result->approved)->toBeFalse()
        ->and($result->failureReason)->toContain('declined');
});

it('is idempotent: same key returns the same transaction id', function () {
    $processor = new FakeBluePayProcessor;
    $a = $processor->charge('mc_5555', 50_00, 'idem-3');
    $b = $processor->charge('mc_5555', 50_00, 'idem-3');

    expect($a->transactionId)->toBe($b->transactionId);
});

it('refunds an existing transaction successfully', function () {
    $processor = new FakeBluePayProcessor;
    $charge = $processor->charge('amex_3434', 200_00, 'idem-4');

    $refund = $processor->refund($charge->transactionId, 200_00, 'refund-1');

    expect($refund->approved)->toBeTrue()
        ->and($refund->transactionId)->toStartWith('bp_refund_')
        ->and($refund->cardBrand)->toBe('amex');
});

it('refuses a refund against an unknown transaction id', function () {
    $result = (new FakeBluePayProcessor)->refund('bp_unknown', 100_00, 'refund-2');

    expect($result->approved)->toBeFalse()
        ->and($result->failureReason)->toContain('not found');
});
