<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use App\Services\Accounting\InvoiceService;

/**
 * Refresh the related invoice's paid_cents and status on each captured
 * payment so the A/R ledger tracks cash movement.
 */
class RefreshInvoiceOnPayment
{
    public function __construct(protected InvoiceService $invoices) {}

    public function handle(PaymentCaptured $event): void
    {
        $order = $event->payment->order;
        if (! $order instanceof ExhibitorOrder) {
            return;
        }

        $invoice = Invoice::query()
            ->where('invoiceable_type', ExhibitorOrder::class)
            ->where('invoiceable_id', $order->id)
            ->first();

        if ($invoice === null) {
            return;
        }

        $this->invoices->refreshFromSource($invoice);
    }
}
