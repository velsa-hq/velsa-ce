---
title: Event settlement document
section: Bookings
order: 50
surfaces:
  - route: /bookings/{booking}/settlement.pdf
    method: GET
  - component: bookings/show
tour_ids:
  - bk-settlement-pdf
---

:::video settle-a-booking

After an event closes, the **Settlement PDF** button on the booking
show page produces a one-document reconciliation of everything that
happened financially for that booking. It's the artifact you hand off
to finance (or to the Clerk of Court for the monthly summary): one
page per booking that ties every charge, invoice, and payment
together with the resulting net position.

## What the document includes

**Event metadata** - booking reference, name, venue, client, dates,
attendance estimate vs. actual, status.

**Charges** - every chargeable item attached to the event:

- Booking fee (the `total_cents` on the booking itself)
- Exhibitor orders (rolled up across all the booking's
  exhibitor events - count + total)

**Invoices issued** - every invoice the system raised for this
booking, including invoices issued against any exhibitor order that
rolls up to the booking's exhibitor events. Each row shows invoice
number, source kind, issue date, status, total, paid, and balance.

**Payments received** - every payment captured against any exhibitor
order under this booking, with refunds called out per row. Booking-
level direct payments (check / wire / cash applied via the invoice
page) don't have a separate payment ledger row; their movement is
visible through the invoice's `paid_cents` walking forward.

**Settlement block** - the bottom-of-page roll-up:

| Line | Source |
| --- | --- |
| **Total invoiced** | Sum of `invoice.total_cents` across booking-sourced and exhibitor-order-sourced invoices |
| **Total payments received** | Gross - what was captured before refunds |
| **Refunds posted** | Audit-trail line; refunds have already walked the invoice paid-state back, so this is informational |
| **Net collected** | Canonical "paid" - sum of `invoice.paid_cents`, post-refund |
| **Outstanding balance** | Total invoiced minus net collected; rendered in red when non-zero |

## When to generate it

- **At event close**, hand it to the finance team alongside the
  raw invoice list.
- **For the Clerk of Court monthly handoff**, batch the settlement
  PDFs for every event that closed in the month.
- **For client-side close-out conversations** where you want to
  summarize the full financial picture without diving through each
  invoice individually.

## How to access

From `/bookings/{id}`, click **Settlement PDF** in the action row at
the top of the page (alongside Edit / Floor plan / Run-of-show). The
PDF opens in a new tab - save or print from there.

There's no admin gate beyond "authenticated user"; permission scoping
follows the same model as the rest of the booking show page.
