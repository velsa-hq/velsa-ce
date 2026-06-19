<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Models\JournalEntry;

/**
 * Posts the two-leg entry for a captured payment: DR cash, CR A/R.
 *
 * Revenue is recognized at invoice issuance, so a payment only moves money
 * from receivable to cash. Crediting revenue here would double-count it.
 */
class PostPaymentJournalEntries
{
    public function handle(PaymentCaptured $event): void
    {
        $payment = $event->payment;
        $order = $payment->order;
        $venueId = $order?->exhibitor?->event?->booking?->venue_id;
        $fund = config('accounting.posting.default_fund');

        $description = sprintf(
            'Exhibitor payment %s (order %s)',
            $payment->provider_transaction_id ?? '#'.$payment->id,
            $order?->order_number ?? '#'.$order?->id,
        );

        JournalEntry::post([
            'venue_id' => $venueId,
            'fund_code' => $fund,
            'source_type' => $payment::class,
            'source_id' => $payment->id,
            'account_code' => config('accounting.posting.cash_account', '1010'),
            'description' => $description,
            'debit_cents' => $payment->amount_cents,
            'credit_cents' => 0,
        ]);

        JournalEntry::post([
            'venue_id' => $venueId,
            'fund_code' => $fund,
            'source_type' => $payment::class,
            'source_id' => $payment->id,
            'account_code' => config('accounting.posting.ar_account', '1100'),
            'description' => $description,
            'debit_cents' => 0,
            'credit_cents' => $payment->amount_cents,
        ]);
    }
}
