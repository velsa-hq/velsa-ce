---
title: Invoicing lifecycle
section: Accounting
order: 46
surfaces:
  - route: /admin/invoices
    method: GET
  - route: /admin/invoices/{invoice}
    method: GET
  - component: admin/invoices/index
  - component: admin/invoices/show
tour_ids:
  - inv-record-payment
  - inv-write-off
  - inv-void
---

Invoices live at `/admin/invoices`. A single polymorphic `Invoice`
model handles four sources today:

- **Exhibitor orders** - auto-issued when an exhibitor adds the
  first item to an order
- **Booking deposits** - issued from the booking page once the
  booking is Definite (or via the admin "Issue deposit" action).
  Idempotency key: `notes = 'deposit'`
- **Booking balances** - issued for the remaining amount after the
  deposit is paid (or for the full amount if no deposit).
  Idempotency key: `notes = 'balance'`
- **Booking installments** - issued by the daily
  `installments:issue-due` artisan command for each `Installment`
  whose `due_date <= today` and `invoice_id IS NULL`. Idempotency
  key: `notes = 'installment_<n>'` so a re-run of the daily sweep
  returns the existing invoice instead of issuing a second one.
  See [Payment schedules + installments](/docs/accounting/payment-schedules)
  for the schedule shape that feeds this path.

:::video invoicing

Every invoice gets a human-readable number on issuance (e.g.
`INV-2026-A4F7Z`) and surfaces on the issuing party's dashboard.

On every new booking-invoice creation the system also dispatches an
**Issued invoice** email to the booking's primary contact (Booking
-> Client -> primary `Contact` -> email, falling back to any contact
email). The idempotent re-run paths above do *not* re-email - only
the first creation does. If no email is on file the dispatch is
skipped silently; the audit row still captures the issuance.

## Status lifecycle

| Status | Meaning | Can refund? |
| --- | --- | --- |
| Draft | Not yet issued; no journal entries posted | No |
| Issued | Sent to the customer; AR posted (debit 1100, credit 4xxx revenue) | When paid |
| Partial paid | One or more payments applied but balance > 0 | Yes |
| Paid | balance = 0 | Yes |
| Past due | Issued, balance > 0, due date in the past | When partly paid |
| Void | Issued in error; reverses the original AR posting | No |
| Written off | Uncollectable; debit 5900 Bad Debt, credit AR 1100 | No |

## Revenue recognition

Velsa recognizes revenue on the **accrual basis**: issuing an invoice
posts the journal entry automatically - **debit A/R (1100)** for the
total, **credit revenue** for the subtotal, and **credit Sales Tax
Payable (2200)** for the tax portion. Recording a payment then debits
Cash and credits A/R, so a fully-paid invoice leaves A/R at zero with
revenue recognized and cash collected.

The revenue account is chosen by the invoice's source and is
configurable in `config/accounting.php` (`posting.revenue_accounts`):
exhibitor orders -> **4300 Exhibitor Revenue**, bookings -> **4100 Venue
Rental Revenue**, everything else -> **4900 Other Revenue**.

**Void** reverses the issuance entry in full; **write-off** moves the
unpaid remainder to bad debt while leaving the recognized revenue in
place (you earned it - the customer just didn't pay).

> Invoices issued before accrual posting was enabled can be backfilled
> with `php artisan accounting:backfill-issuance` (idempotent; skips
> drafts and voids). Run `--dry-run` first to see the count.

## Common actions

- **Issue from booking** - `/bookings/{id}` -> "Issue deposit invoice"
  or "Issue balance invoice". Picks the deposit percent off the
  booking and posts AR.
- **Send (mark as sent)** - stamps the sent timestamp and emails the
  invoice to the primary contact for the source (the client for
  bookings, the exhibitor for orders).
- **Record payment** - see [Payments](/docs/accounting/payments)
- **Refund** - see [Refunds](/docs/accounting/refunds)
- **Void** - for invoices issued in error. Reverses the original AR
  posting. Does **not** refund any payments already applied; if
  payments exist you should refund them first.
- **Write off** - for uncollectable balances. Terminal state. The
  **Write off** action on the invoice page posts debit 5900 Bad Debt
  Expense, credit 1100 AR for the remaining balance. Available on any
  open invoice with an outstanding balance (`issued`, `partial_paid`,
  or `past_due`); not on drafts, paid, void, or already-written-off
  invoices.
- **Download PDF** - the **Download PDF** button on the show page
  renders a print-ready PDF using your organization's branding (app
  name, login-page subtitle, From-email) for the header, the source
  party (exhibitor or client) for the bill-to, and either itemized
  order lines or a single booking-summary line in the body. Opens in
  a new tab; save or print from there.

## Line items and references

Every invoice carries a **line items** table - `description`, `qty`,
`unit price`, `total`, plus an optional per-line `reference` (a cost
code or GL account). Auto-populated on issue:

- **Exhibitor orders** fan into one line per `ExhibitorOrderItem`,
  carrying each item's qty and unit price
- **Booking deposit / balance** invoices get a single descriptive
  line ("Booking deposit - BK-2026-XYZ") with the event name as the
  sub-line detail

Two **invoice-level reference fields** appear in their own card on
the show page:

- **Customer reference** - their PO number / billing reference,
  rendered prominently in the PDF header
- **Internal reference** - our project code, event code, or
  whatever the org tracks budget against

Both stay editable in every status (so finance can attach a PO even
after issuance). Per-line `reference` fields aren't editable from
the UI yet - they're available on the model for future surfaces and
get backfilled from sources where present.

## Statements

Per-party statements live at `/admin/exhibitors/{exhibitor}/statement`
(and equivalent paths for clients). The statement renders every
invoice tied to that party with running aging buckets (current / 1-30
/ 31-60 / 61-90 / 90+) and totals.
