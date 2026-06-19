---
title: Exhibitors overview
section: Exhibitors
order: 55
surfaces:
  - route: /exhibitors
    method: GET
  - route: /exhibitors
    method: POST
  - route: /exhibitors/{exhibitor}
    method: PATCH
  - route: /exhibitors/{exhibitor}
    method: DELETE
  - route: /exhibitor-events
    method: POST
  - route: /exhibitor-events/{event}
    method: PATCH
  - route: /exhibitor-events/{event}
    method: DELETE
  - component: exhibitors/index
  - component: exhibitors/show
  - component: exhibitors/event-show
tour_ids:
  - ex-new
  - ex-new-event
  - ex-edit
  - ev-edit
---

:::video exhibitors-admin

The Exhibitors section manages the third-party vendors who rent
equipment + space at your events - trade-show vendors, food trucks,
equestrian-show exhibitors, fair vendors, etc. Each exhibitor
attaches to a specific **exhibitor event** (the parent show or fair)
and places **orders** against the venue's equipment catalog.

## Where things live

| Surface | Path | Use it to... |
| --- | --- | --- |
| Exhibitor index | `/exhibitors` | Browse every exhibitor across events |
| Event roster | `/exhibitor-events/{id}` | See every exhibitor attached to one event |
| Exhibitor detail | `/exhibitors/{id}` | Order history, balance, contacts |
| Order detail | `/exhibitors/{id}/orders/{order}` | Add/remove items, see status |
| Exhibitor portal | `/portal` | The external surface where exhibitors self-serve |
| Equipment catalog | (admin -> venues) | What's available to rent |

## Core concepts

**Exhibitor event** is the parent - a single fair, trade show, or
expo. Each event has a date window, an associated venue, and a
collection of exhibitors.

**Exhibitor** is a vendor attached to one event. The same physical
company can exhibit at multiple events; each appearance is a separate
record so contact info, booth assignments, and orders stay event-
scoped.

**Exhibitor order** is the bill of equipment and services the
exhibitor has reserved. Orders move through Pending -> Partially paid ->
Paid as payments land (with Cancelled and Refunded as the off-ramps).
Each order has line items pulled from the equipment catalog with a
quantity and a rate snapshot, so price changes don't retroactively
affect issued orders.

**Equipment master** is the venue-scoped catalog of bookable items -
chairs, tables, tents, generators, A/V kits, etc. - with rate cards
and inventory limits. See [Equipment catalog](/docs/exhibitors/equipment-catalog).

## Managing exhibitors + events (staff)

Everything can be created and maintained from the admin side - you no
longer have to wait for an exhibitor to self-register.

- **New event** (`/exhibitors` -> **+ New event**) attaches an exhibitor
  hall to a booking. Pick the booking (expo / trade-show bookings sort
  first), set a default booth size, and optionally a registration
  window. The **portal slug** auto-derives from the name if you leave it
  blank, and is made unique automatically. You can't create exhibitors
  until at least one event exists.
- **New exhibitor** (`/exhibitors` -> **+ New exhibitor**, or **+ New
  exhibitor** on an event roster, which prefills the event) captures
  company, contact, email, phone, and booth assignment / size.
- **Edit / Delete** live on the exhibitor and event detail pages. An
  exhibitor with recorded payments can't be deleted (refund + void
  first); an event with exhibitors still attached can't be deleted.
- **Filtering**: the status cards at the top of the index toggle a
  status filter; a **Clear filter** affordance appears while one is
  active. The event dropdown narrows to a single show.

The exhibitor detail page also surfaces **work-order completion** for
the exhibitor's event - the booking's work orders with an "N/M
complete" rollup - so you can see fulfillment status alongside booth
and payment information.

## What flows through the financial system

When an exhibitor adds the first item to an order, the system
automatically issues an invoice against that order. Payments and
refunds posted against an exhibitor order roll up to:

- The payment record (card or manual)
- The order's running paid total
- The invoice's paid total (kept in sync automatically)

See [Invoicing lifecycle](/docs/accounting/invoicing) for the rest of
the AR flow.

## Where to go next

- [Orders](/docs/exhibitors/orders) - placing + managing
- [Order fulfillment](/docs/exhibitors/fulfillment) - how orders hand off
  to the floor as work orders, and completion comes back here
- [Equipment catalog](/docs/exhibitors/equipment-catalog) - what's available
- [Exhibitor portal](/docs/exhibitors/portal) - what your exhibitors see
