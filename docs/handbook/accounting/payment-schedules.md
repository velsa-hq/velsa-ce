---
title: Payment schedules + installments
section: Accounting
order: 57
surfaces:
  - route: /bookings/{booking}/payment-schedule
    method: PUT
  - route: /payment-schedules/{paymentSchedule}
    method: DELETE
  - component: bookings/show
tour_ids:
  - bk-payment-schedule-add-installment
  - bk-payment-schedule-save
  - bk-payment-schedule-edit
  - bk-payment-schedule-delete
---

:::video payment-schedule

A **payment schedule** breaks a booking's total into N installments,
each with its own due date. Once a due date arrives, the system
issues an invoice for that installment automatically and emails the
client. Generalizes the simpler deposit-and-balance flow on a
booking; pick one or the other per booking, not both.

Lives on the booking show page as a subsection of the **Billing**
card: `/bookings/{id}` -> Billing -> Payment schedule.

## When to use it

Use a payment schedule when an event needs more than two payment
points - for example, a 12-month conference cycle with quarterly
checkpoints, or a large wedding with deposit + venue payment + AV
deposit + final balance. For a simple deposit/balance event, the
**deposit percent** field on the booking is faster.

The two flows are mutually exclusive. The schedule form refuses to
save while the booking still has a nonzero deposit percent - clear
the deposit percent first, then add the schedule.

## Installment lifecycle

| State | Meaning | Editable? |
| --- | --- | --- |
| Pending due date | Created; `invoice_id` and `paid_at` are null | Yes |
| Invoiced | Daily job issued the invoice; `invoice_id` + `invoiced_at` filled | No |
| Paid | Issued invoice was settled in full; `paid_at` filled by observer | No |

Once an installment is invoiced it freezes. Adjusting it (or
deleting it) requires voiding the issued invoice first, which clears
`invoice_id` and unlocks the row again.

## Common actions

- **Create a schedule** - open the booking, scroll to the Payment
  schedule subsection of Billing. With no schedule yet, the form is
  already open with two suggested 50/50 rows; fill in dates and
  amounts, click **Save schedule**.
- **Edit an existing schedule** - the saved schedule renders as a
  read-only table; click **Edit schedule** at the bottom of the
  table to flip back into the form. Each row's label, due date, and
  amount become editable; rows whose installment has been invoiced
  stay read-only even in edit mode.
- **Add or remove a row** - **Add installment** appends a new row;
  **Remove** on a row deletes it. Removed rows that were never
  invoiced disappear; rows already invoiced refuse to delete.
- **Delete the schedule** - **Delete schedule** at the bottom of
  the table wipes all un-invoiced installments. Refuses if any
  installment has been invoiced.

## Auto-invoice flow

A daily artisan command (`installments:issue-due`, scheduled in
`routes/console.php`) walks every installment whose `due_date <= today`
and `invoice_id IS NULL`, then:

1. Issues a Booking invoice via `InvoiceService::issueInstallmentForBooking`
   with `notes='installment_<n>'` as the idempotency key (re-runs
   return the same invoice instead of duplicating).
2. Stamps `invoice_id` + `invoiced_at` on the installment row.
3. The standard issued-invoice email goes out to the client per the
   existing dunning + receipt flow (see [Invoicing](/docs/accounting/invoicing)).

When the issued invoice gets fully paid (by any means - Pay Now,
manual recording, refund), the `InvoiceObserver` stamps `paid_at` on
the installment so the schedule UI shows the paid state without
having to walk the invoices table.

## Sum-of-installments check

The form shows the running total under the rows alongside the
booking's total. A mismatch surfaces an amber chip ("doesn't match
booking total") but doesn't block saving - sometimes you intend a
mismatch (e.g. installments cover the booking fee but exhibitor
revenue lands separately). The chip is informational.

## See also

- [Invoicing lifecycle](/docs/accounting/invoicing) - what happens
  to each auto-issued invoice
- [Payments](/docs/accounting/payments) - how a client paying an
  installment invoice flows back to the schedule
- [Dunning](/docs/accounting/dunning) - installment invoices use
  the same dunning escalation as any other invoice
