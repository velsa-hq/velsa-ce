---
title: Refunds
section: Accounting
order: 48
---

Two refund paths, mirroring the two payment paths.

## Per-payment refund (exhibitor orders)

For invoices sourced from an exhibitor order, each captured payment
gets a **Refund** button on `/admin/invoices/{number}`. The form
takes an amount (clamped to the refundable remaining on that payment)
and an optional reason.

What happens:

1. If the payment was a card capture, the processor is asked to issue
   the refund with an idempotency key; the processor must approve.
2. If the payment was manual (check, wire, cash, ACH), no processor
   round-trip - finance is responsible for cutting the refund check
   or initiating the wire reversal externally.
3. The refunded amount accumulates against the original payment;
   subsequent partial refunds extend it until the payment is fully
   refunded.
4. The order's paid amount walks back; status transitions Paid ->
   Partially paid -> Pending as appropriate.
5. A reversing journal pair posts (debit AR, credit Cash).
6. The invoice refreshes so its paid amount and status stay in sync.
7. An audit entry records the refund.

Partial refunds are supported. A fully-refunded payment refuses
further refunds with "no refundable amount remaining."

## Invoice-level refund (booking + client)

For booking-sourced invoices there's no separate payment row to
point at - the invoice tracks its own paid amount directly. The
amber **Refund invoice** card appears on the invoice show page when
the invoice has been paid (in part or in full) and the source isn't
an exhibitor order.

What happens:

1. The amount is validated and clamped to the invoice's paid total
   so an overshoot can't refund more than was captured.
2. The invoice's paid amount walks back; status transitions Paid ->
   Partial paid -> Issued (or stays Past due if applicable).
3. The "paid on" date clears if the invoice is no longer fully paid.
4. A reversing journal pair posts (debit AR, credit Cash).
5. An audit entry records the refund.

Booking-invoice refunds **do not** trigger a processor round-trip
(there isn't one to round-trip with) and **do not** currently send a
refund email. The audit entry + journal entries are the durable
record; finance handles the physical reversal externally.

## What can't be refunded

- Void invoices - there's nothing posted to reverse
- Written-off invoices - already terminal
- Draft / Issued invoices with no payment yet - nothing to refund
- Non-positive amounts - refused with "must be positive"

## Audit trail

Both refund paths write to the audit log with the actor, amount,
reason, and references. Filter `/admin/audit` on the refund event
types to see the history.
