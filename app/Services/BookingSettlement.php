<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorOrder;
use App\Models\ExhibitorPayment;
use App\Models\Invoice;

/**
 * Assembles the post-event settlement view of a booking: charges, invoices,
 * payments, refunds, and the resulting net position. Rendered by the PDF
 * template; the struct is also reused by the show page, export, and email.
 */
class BookingSettlement
{
    /**
     * @return array{
     *   booking: Booking,
     *   charges: list<array{label:string,detail:string,amount_cents:int}>,
     *   charges_subtotal_cents: int,
     *   invoices: list<array{
     *     id:int, number:string, source:string, status:?string,
     *     total_cents:int, paid_cents:int, balance_cents:int,
     *     issued_on:?string, refunded_cents:int
     *   }>,
     *   payments: list<array{id:int,date:?string,method:string,amount_cents:int,refunded_cents:int}>,
     *   totals: array{
     *     invoiced_cents:int, paid_cents:int, refunded_cents:int,
     *     net_collected_cents:int, outstanding_cents:int
     *   }
     * }
     */
    public function forBooking(Booking $booking): array
    {
        $booking->loadMissing([
            'venue:id,name',
            'client:id,name',
            'invoices',
        ]);

        // -- Charges ----------------------------------------------
        $charges = [];
        if ($booking->total_cents > 0) {
            $charges[] = [
                'label' => 'Booking fee',
                'detail' => $booking->reference.' - '.($booking->name ?? 'Event'),
                'amount_cents' => (int) $booking->total_cents,
            ];
        }

        $exhibitorEvents = ExhibitorEvent::query()
            ->where('booking_id', $booking->id)
            ->get();

        $exhibitorOrders = collect();
        if ($exhibitorEvents->isNotEmpty()) {
            $exhibitorOrders = ExhibitorOrder::query()
                ->whereHas('exhibitor', fn ($q) => $q->whereIn(
                    'exhibitor_event_id',
                    $exhibitorEvents->pluck('id'),
                ))
                ->get();

            if ($exhibitorOrders->isNotEmpty()) {
                $charges[] = [
                    'label' => 'Exhibitor orders',
                    'detail' => $exhibitorOrders->count().' order'
                        .($exhibitorOrders->count() === 1 ? '' : 's')
                        .' across '.$exhibitorEvents->count().' event'
                        .($exhibitorEvents->count() === 1 ? '' : 's'),
                    'amount_cents' => (int) $exhibitorOrders->sum('total_cents'),
                ];
            }
        }

        $chargesSubtotal = array_sum(array_column($charges, 'amount_cents'));

        // -- Invoices ---------------------------------------------
        // booking-sourced invoices plus invoices for exhibitor orders rolled up
        // to this booking's exhibitor events
        $bookingInvoices = $booking->invoices;
        $exhibitorInvoices = $exhibitorOrders->isEmpty()
            ? collect()
            : Invoice::query()
                ->where('invoiceable_type', ExhibitorOrder::class)
                ->whereIn('invoiceable_id', $exhibitorOrders->pluck('id'))
                ->get();

        $allInvoices = $bookingInvoices->concat($exhibitorInvoices);

        $invoiceRows = $allInvoices->map(function (Invoice $inv) {
            $source = match (true) {
                $inv->invoiceable_type === ExhibitorOrder::class => 'Exhibitor order',
                $inv->invoiceable_type === Booking::class => 'Booking',
                default => class_basename((string) $inv->invoiceable_type),
            };

            return [
                'id' => (int) $inv->id,
                'number' => (string) $inv->number,
                'source' => $source,
                'status' => $inv->status?->value,
                'total_cents' => (int) $inv->total_cents,
                'paid_cents' => (int) $inv->paid_cents,
                'balance_cents' => $inv->balanceCents(),
                'issued_on' => $inv->issued_on?->toDateString(),
                'refunded_cents' => 0, // recomputed below from payment-level data
            ];
        })->values()->all();

        // -- Payments + refunds -----------------------------------
        $payments = $exhibitorOrders->isEmpty()
            ? collect()
            : ExhibitorPayment::query()
                ->whereIn('exhibitor_order_id', $exhibitorOrders->pluck('id'))
                ->orderBy('processed_at')
                ->get();

        $paymentRows = $payments->map(fn (ExhibitorPayment $p) => [
            'id' => (int) $p->id,
            'date' => $p->processed_at?->toDateString(),
            'method' => $p->card_brand ?? $p->provider ?? 'manual',
            'amount_cents' => (int) $p->amount_cents,
            'refunded_cents' => (int) $p->refunded_amount_cents,
        ])->all();

        // -- Roll-ups ---------------------------------------------
        // invoice.paid_cents is the canonical post-refund state (the refund flow
        // walks it back), so net_collected == sum(paid_cents); the payment-ledger
        // refund total is surfaced separately as an audit trail, not subtracted again
        $invoicedCents = (int) $allInvoices->sum('total_cents');
        $netCollected = (int) $allInvoices->sum('paid_cents');
        $refundedCents = (int) $payments->sum('refunded_amount_cents');
        $paidCents = $netCollected + $refundedCents; // gross, for display
        $outstanding = max(0, $invoicedCents - $netCollected);

        return [
            'booking' => $booking,
            'charges' => $charges,
            'charges_subtotal_cents' => $chargesSubtotal,
            'invoices' => $invoiceRows,
            'payments' => $paymentRows,
            'totals' => [
                'invoiced_cents' => $invoicedCents,
                'paid_cents' => $paidCents,
                'refunded_cents' => $refundedCents,
                'net_collected_cents' => $netCollected,
                'outstanding_cents' => $outstanding,
            ],
        ];
    }
}
