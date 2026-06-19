<?php

use App\Enums\ExhibitorOrderStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditEvent;
use App\Models\ExhibitorOrder;
use App\Services\Payments\OrderPaymentService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FundsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(FundsSeeder::class);
    $this->seed(ChartOfAccountsSeeder::class);
});

it('captures a full-balance charge and emits a payment.processed audit row', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    $payment = app(OrderPaymentService::class)->charge($order, 'visa_4242');

    expect($payment->status)->toBe(PaymentStatus::Captured)
        ->and($payment->amount_cents)->toBe(100_00)
        ->and($payment->last4)->toBe('4242')
        ->and($order->fresh()->status)->toBe(ExhibitorOrderStatus::Paid)
        ->and($order->fresh()->paid_cents)->toBe(100_00)
        ->and(AuditEvent::query()->where('event_type', 'payment.processed')->count())->toBe(1);
});

it('clamps a charge to the outstanding balance', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    $payment = app(OrderPaymentService::class)->charge($order, 'visa_4242', 250_00);

    expect($payment->amount_cents)->toBe(100_00)
        ->and($order->fresh()->paid_cents)->toBe(100_00)
        ->and($order->fresh()->status)->toBe(ExhibitorOrderStatus::Paid);
});

it('records a failed payment without applying to the order, and emits payment.failed', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    $payment = app(OrderPaymentService::class)->charge($order, 'test_0000');

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toContain('declined')
        ->and($order->fresh()->paid_cents)->toBe(0)
        ->and($order->fresh()->status)->toBe(ExhibitorOrderStatus::Pending)
        ->and(AuditEvent::query()->where('event_type', 'payment.failed')->count())->toBe(1);
});

it('charges a partial amount and keeps the order at PartiallyPaid', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 200_00,
        'paid_cents' => 0,
        'status' => ExhibitorOrderStatus::Pending->value,
    ]);

    app(OrderPaymentService::class)->charge($order, 'visa_4242', amountCents: 100_00);

    expect($order->fresh()->status)->toBe(ExhibitorOrderStatus::PartiallyPaid)
        ->and($order->fresh()->paid_cents)->toBe(100_00)
        ->and($order->fresh()->balanceCents())->toBe(100_00);
});

it('throws if the order has no balance to charge', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 100_00,
        'paid_cents' => 100_00,
        'status' => ExhibitorOrderStatus::Paid->value,
    ]);

    expect(fn () => app(OrderPaymentService::class)->charge($order, 'visa_4242'))
        ->toThrow(RuntimeException::class, 'no balance');
});

it('writes a unique idempotency key per attempt', function () {
    $order = ExhibitorOrder::factory()->create([
        'total_cents' => 50_00,
        'paid_cents' => 0,
    ]);

    $a = app(OrderPaymentService::class)->charge($order, 'visa_1', amountCents: 25_00);
    $b = app(OrderPaymentService::class)->charge($order, 'visa_2', amountCents: 25_00);

    expect($a->idempotency_key)->not->toBe($b->idempotency_key);
});
