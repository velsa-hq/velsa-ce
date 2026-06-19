<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Mail\PaymentReceipt;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a receipt to the exhibitor on each captured payment.
 * Skipped when no email is on file (e.g. back-office check/wire).
 */
class SendPaymentReceipt
{
    public function handle(PaymentCaptured $event): void
    {
        $payment = $event->payment->fresh(['order.exhibitor']);
        if ($payment === null) {
            return;
        }

        $order = $payment->order;
        if (! $order instanceof ExhibitorOrder) {
            return;
        }

        $email = $order->exhibitor?->email;
        if (empty($email)) {
            return;
        }

        $invoice = Invoice::query()
            ->where('invoiceable_type', ExhibitorOrder::class)
            ->where('invoiceable_id', $order->id)
            ->first();

        Mail::to($email)->send(new PaymentReceipt($payment, $order->fresh(), $invoice));
    }
}
