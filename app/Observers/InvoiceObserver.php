<?php

namespace App\Observers;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Installment;
use App\Models\Invoice;
use App\Services\AutoNarrative;

class InvoiceObserver
{
    public function __construct(protected AutoNarrative $autoNarrative) {}

    // log issuance only for Booking-sourced invoices; exhibitor orders are out of scope here
    public function created(Invoice $invoice): void
    {
        $booking = $this->bookingFor($invoice);
        if ($booking === null) {
            return;
        }

        $totalDollars = number_format($invoice->total_cents / 100, 2);
        $kind = $invoice->notes ?: 'invoice';

        $this->autoNarrative->append(
            $booking,
            "Invoice {$invoice->number} issued ({$kind}, \${$totalDollars}).",
        );
    }

    public function updated(Invoice $invoice): void
    {
        // void unlinks the installment row so the schedule can be edited again
        if (
            $invoice->wasChanged('status')
            && $invoice->status === InvoiceStatus::Void
        ) {
            Installment::query()
                ->where('invoice_id', $invoice->id)
                ->update([
                    'invoice_id' => null,
                    'invoiced_at' => null,
                ]);
        }

        if (! $invoice->wasChanged('paid_cents')) {
            return;
        }

        $previous = (int) $invoice->getOriginal('paid_cents');
        $current = (int) $invoice->paid_cents;
        $delta = $previous - $current;
        if ($delta > 0) {
            $booking = $this->bookingFor($invoice);
            if ($booking !== null) {
                $deltaDollars = number_format($delta / 100, 2);
                $this->autoNarrative->append(
                    $booking,
                    "Refund of \${$deltaDollars} applied to invoice {$invoice->number}.",
                );
            }

            return;
        }

        // payment: stamp the linked installment's paid_at once fully paid
        if ($current < $invoice->total_cents) {
            return;
        }

        $installment = Installment::query()
            ->where('invoice_id', $invoice->id)
            ->whereNull('paid_at')
            ->first();
        if ($installment !== null) {
            $installment->update(['paid_at' => now()]);
        }
    }

    protected function bookingFor(Invoice $invoice): ?Booking
    {
        $source = $invoice->invoiceable;

        return $source instanceof Booking ? $source : null;
    }
}
