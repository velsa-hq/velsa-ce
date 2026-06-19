<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Events\PaymentCaptured;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Accounting\InvoiceService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Charges an exhibitor order: idempotency key -> processor charge ->
 * persist ExhibitorPayment -> advance order status -> audit.
 */
class OrderPaymentService
{
    public function __construct(
        protected PaymentProcessor $processor,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Record a payment received outside the BluePay rail (check, wire, cash, ACH).
     * Skips the processor but still fires PaymentCaptured so the journal/receipt
     * listeners run.
     */
    public function recordManual(
        ExhibitorOrder $order,
        int $amountCents,
        string $method,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): ExhibitorPayment {
        if ($amountCents <= 0) {
            throw new RuntimeException('Manual payment amount must be positive.');
        }
        if ($amountCents > $order->balanceCents()) {
            $amountCents = $order->balanceCents();
        }

        $payment = ExhibitorPayment::query()->create([
            'exhibitor_order_id' => $order->id,
            'provider' => 'manual',
            'provider_transaction_id' => $reference,
            'status' => PaymentStatus::Captured->value,
            'amount_cents' => $amountCents,
            'card_brand' => $method, // 'check' | 'wire' | 'cash' | 'ach'
            'last4' => null,
            'idempotency_key' => sprintf('manual-%d-%s', $order->id, Str::random(12)),
            'processed_at' => now(),
            'failure_reason' => $note, // co-opted as a free-text note for manual payments
        ]);

        $order->applyPayment($amountCents);

        $this->auditLogger->record(
            eventType: 'payment.recorded_manual',
            subject: $order,
            payload: [
                'payment_id' => $payment->id,
                'amount_cents' => $amountCents,
                'method' => $method,
                'reference' => $reference,
                'recorded_by_user_id' => $userId,
            ],
        );

        // same fan-out as a card capture so journal/invoice/receipt listeners run
        PaymentCaptured::dispatch($payment->fresh());

        return $payment->fresh();
    }

    public function charge(ExhibitorOrder $order, string $cardToken, ?int $amountCents = null): ExhibitorPayment
    {
        $amount = $amountCents ?? $order->balanceCents();

        if ($amount <= 0) {
            throw new RuntimeException('Order has no balance to charge.');
        }

        // never capture past the balance - overpayment pushes paid_cents > total_cents
        if ($amount > $order->balanceCents()) {
            $amount = $order->balanceCents();
        }

        $idempotencyKey = sprintf('exorder-%d-%s', $order->id, Str::random(12));

        $payment = ExhibitorPayment::query()->create([
            'exhibitor_order_id' => $order->id,
            'provider' => 'bluepay',
            'status' => PaymentStatus::Pending->value,
            'amount_cents' => $amount,
            'idempotency_key' => $idempotencyKey,
        ]);

        $result = $this->processor->charge($cardToken, $amount, $idempotencyKey);

        if ($result->approved) {
            $payment->update([
                'status' => PaymentStatus::Captured->value,
                'provider_transaction_id' => $result->transactionId,
                'last4' => $result->last4,
                'card_brand' => $result->cardBrand,
                'processed_at' => now(),
            ]);

            $order->applyPayment($amount);

            $this->auditLogger->record(
                eventType: 'payment.processed',
                subject: $order,
                payload: [
                    'payment_id' => $payment->id,
                    'amount_cents' => $amount,
                    'transaction_id' => $result->transactionId,
                    'last4' => $result->last4,
                    'brand' => $result->cardBrand,
                ],
            );

            PaymentCaptured::dispatch($payment->fresh());
        } else {
            $payment->update([
                'status' => PaymentStatus::Failed->value,
                'failure_reason' => $result->failureReason,
                'processed_at' => now(),
            ]);

            $this->auditLogger->record(
                eventType: 'payment.failed',
                subject: $order,
                payload: [
                    'payment_id' => $payment->id,
                    'reason' => $result->failureReason,
                ],
            );
        }

        return $payment->fresh();
    }

    /**
     * Refund all or part of a captured payment. BluePay payments round-trip the
     * processor; manual payments (check/wire/cash) are internal bookkeeping only.
     * Posts a reversal journal pair (DR AR 1100 / CR Cash 1010), walks back
     * paid_cents, and refreshes the invoice so AR status stays in lockstep.
     */
    public function refund(
        ExhibitorPayment $payment,
        int $amountCents,
        ?string $reason = null,
        ?int $userId = null,
    ): ExhibitorPayment {
        if ($payment->status !== PaymentStatus::Captured) {
            throw new RuntimeException('Only captured payments can be refunded.');
        }

        $refundable = $payment->refundableAmountCents();
        if ($refundable <= 0) {
            throw new RuntimeException('This payment has no refundable amount remaining.');
        }
        if ($amountCents <= 0) {
            throw new RuntimeException('Refund amount must be positive.');
        }
        if ($amountCents > $refundable) {
            $amountCents = $refundable;
        }

        return DB::transaction(function () use ($payment, $amountCents, $reason, $userId) {
            $refundTxId = null;

            if ($payment->provider === 'bluepay' && $payment->provider_transaction_id !== null) {
                $idempotencyKey = sprintf('refund-%d-%s', $payment->id, Str::random(12));
                $result = $this->processor->refund(
                    transactionId: $payment->provider_transaction_id,
                    amountCents: $amountCents,
                    idempotencyKey: $idempotencyKey,
                );
                if (! $result->approved) {
                    throw new RuntimeException(
                        'Refund declined by processor: '.($result->failureReason ?? 'unknown reason'),
                    );
                }
                $refundTxId = $result->transactionId;
            }
            // provider=manual: no processor round-trip; finance reverses externally

            $payment->update([
                'refunded_amount_cents' => $payment->refunded_amount_cents + $amountCents,
                'refunded_at' => now(),
            ]);

            $order = $payment->order;
            if ($order instanceof ExhibitorOrder) {
                $order->reversePayment($amountCents);
            }

            // reversal of the capture (DR AR / CR Cash); same venue + fund tags as
            // the capture leg so per-venue/fund reporting stays consistent
            $method = $payment->card_brand ?? 'card';
            $refRef = $refundTxId ?? 'manual-refund-'.$payment->id;
            $venueId = $order?->exhibitor?->event?->booking?->venue_id;
            $fund = config('accounting.posting.default_fund');
            JournalEntry::post([
                'venue_id' => $venueId,
                'fund_code' => $fund,
                'account_code' => config('accounting.posting.ar_account', '1100'),
                'description' => "Refund issued - payment #{$payment->id} ({$method}, ref {$refRef})",
                'debit_cents' => $amountCents,
                'source_type' => ExhibitorPayment::class,
                'source_id' => $payment->id,
                'posted_by_user_id' => $userId,
            ]);
            JournalEntry::post([
                'venue_id' => $venueId,
                'fund_code' => $fund,
                'account_code' => config('accounting.posting.cash_account', '1010'),
                'description' => "Cash out - refund of payment #{$payment->id}",
                'credit_cents' => $amountCents,
                'source_type' => ExhibitorPayment::class,
                'source_id' => $payment->id,
                'posted_by_user_id' => $userId,
            ]);

            $this->auditLogger->record(
                eventType: 'payment.refunded',
                subject: $order ?? $payment,
                payload: [
                    'payment_id' => $payment->id,
                    'amount_cents' => $amountCents,
                    'refund_transaction_id' => $refundTxId,
                    'reason' => $reason,
                    'refunded_by_user_id' => $userId,
                ],
            );

            // refresh the invoice so paid_cents + status reflect the reversal, then notify
            if ($order instanceof ExhibitorOrder) {
                $invoice = Invoice::query()
                    ->where('invoiceable_type', ExhibitorOrder::class)
                    ->where('invoiceable_id', $order->id)
                    ->first();
                if ($invoice !== null) {
                    $service = app(InvoiceService::class);
                    $service->refreshFromSource($invoice);
                    $service->sendRefundNotice($invoice->fresh(), $amountCents, $reason);
                }
            }

            return $payment->fresh();
        });
    }
}
