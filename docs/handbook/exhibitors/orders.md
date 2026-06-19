---
title: Exhibitor orders
section: Exhibitors
order: 56
surfaces:
  - route: /exhibitors/{id}/orders/{order}
    method: GET
  - route: /exhibitor-events
    method: POST
  - route: /exhibitor-events/{event}
    method: PATCH
  - route: /portal/catalog
    method: GET
  - component: pages/exhibitors/event-form-modal
  - component: pages/portal/catalog
tour_ids:
  - ev-advance-deadline
  - ev-surcharge-pct
  - portal-pricing-banner
  - portal-item-advance-price
  - portal-item-standard-price
---

An exhibitor order is the running bill of equipment + services an
exhibitor has reserved for a specific event. Staff manage orders from
`/exhibitors/{id}/orders/{order}`; exhibitors self-serve from the
[portal](/docs/exhibitors/portal).

:::video exhibitor-advance-rates

## Order lifecycle

| Status | Meaning |
| --- | --- |
| Cart | Started in the portal, not yet submitted |
| Pending | Items added, nothing paid |
| Partially paid | At least one payment captured but balance > 0 |
| Paid | balance = 0 |
| Refunded | A captured payment was reversed |
| Cancelled | Exhibitor withdrew; manually reversed |

The paid / partially-paid / paid / refunded states are **driven by the
payment machinery** - they follow the money and aren't set by hand. The
two you set manually are **Cancel** and **Reopen** (see below).

## Adding items

Staff:

- `/exhibitors/{id}/orders/{order}` -> **Add item** form
- Pick an item from the venue's equipment catalog
- Enter a quantity
- Submit -> the line is added at the catalog rate **as of right now**
  (price snapshot is preserved against future rate changes)

Exhibitor (portal):

- `/portal/catalog` -> **Add to order** on any catalog row
- Quantity selector on the order page; same snapshot behavior

The very first item added to an order automatically issues an invoice
- admins don't need to click "Issue invoice" separately. Subsequent
items revise the invoice's totals automatically.

## Editing + removing items

From either surface, remove a line and the order's totals + the synced
invoice recompute. Staff can also **edit a line quantity inline** on the
order page (type the new quantity, press Enter or the check). Once an
order is **paid or refunded**, item add / edit / remove are locked to
protect the financial record.

Refunds for items already paid run through the refund flow below -
removing the line alone doesn't refund the money.

## Payment + refunds (staff)

Staff can take and reverse payments straight from the order page -
**Record payment** and the per-payment **Refund** action - all of which
run through the same payment service the portal uses:

- **Card (BluePay)** - capture against a card token (in production this
  comes from the BluePay hosted field; the dev driver accepts any token
  whose last four are non-zero digits).
- **Manual** - record a check / wire / cash / ACH payment with an
  optional reference and note.
- **Refund** - full or partial against any captured payment, with an
  optional reason. Posts the reversing journal pair and refreshes the
  invoice automatically.

Exhibitors can also pay their own balance from the portal (BluePay).
See [Recording payments](/docs/accounting/payments) and the
[refund flow](/docs/accounting/refunds) for the accounting side.

## Cancelling + reopening

**Cancel order** is available on an order with no recorded payments;
refund any payments first. A cancelled order can be **Reopened** back to
pending.

## Portal access

To let an exhibitor place their own order:

1. `/exhibitors/{id}` -> **Issue portal link**
2. The system generates a single-use magic token and emails it to the
   exhibitor's primary email
3. Token-redeem creates an authenticated `exhibitor` guard session;
   they land on `/portal`

Exhibitors can also request their own link from the public
[`/portal/access`](/docs/exhibitors/portal) page. Tokens are scoped +
time-limited (default 7 days) and single-use. See
[Exhibitor portal](/docs/exhibitors/portal).

## Advance-rate pricing (order deadlines)

An exhibitor event can set an **advance-rate deadline** and a **late-order
surcharge %** (on the event form). Orders placed on or before the deadline get
the catalog's advance (base) price; line items added **after** the deadline carry
the surcharge automatically. The exhibitor portal shows a banner - "Order by
{date} for advance rates" before the deadline, and a "{X}% surcharge applies"
notice after - and each catalog item shows its advance and standard prices.

> Surcharge is event-wide (a single %). Per-item advance/standard prices are a
> planned follow-up (they need a catalog editor for the exhibitor equipment list).

## Statements

Each exhibitor has a running statement at
`/admin/exhibitors/{id}/statement` showing every invoice they've been
issued with aging buckets and totals.
