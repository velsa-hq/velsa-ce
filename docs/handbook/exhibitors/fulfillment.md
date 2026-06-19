---
title: Exhibitor order fulfillment
section: Exhibitors
order: 57
surfaces:
  - route: /exhibitors/{exhibitor}/orders/{order}
    method: PATCH
  - component: exhibitors/show
  - component: exhibitors/order-show
  - component: work-orders/show
tour_ids:
  - ex-fulfillment-rollup
  - wo-exhibitor-source
---

When an exhibitor order is confirmed, the system hands the physical
work off to your floor. It generates the **work orders** your crews
need to set up that exhibitor's booth, and tracks their completion
back on the exhibitor's summary - so the person taking orders and the
people working the floor stay in sync without anyone re-keying a
thing.

## From order to floor

Confirming an order turns its line items into work for the venue. The
system groups the order's items by **department** and creates one work
order per department, because that's how the floor actually works - the
electricians run the power drops, the decorators set the tables and
chairs.

Each generated work order is:

- **Tagged with the department** so the right crew can pick it up (assign
  a specific owner on the work order itself if you want one named),
- **Scheduled** for the event's setup window (the booking's start),
- **Labeled with the booth** so the crew knows exactly where to go, and
- **Itemized** - every order line becomes a work-order item (a *deploy*
  action) carrying the SKU, name, and quantity.

For example, an order for *1 power drop, 2 banquet chairs, 1 6′ table*
becomes:

| Work order | Department | Items |
| --- | --- | --- |
| Booth 214 - Electrical setup | Electrical | 1× power drop |
| Booth 214 - Furniture setup | Furniture | 2× banquet chair, 1× 6′ table |

Every generated work order links back to the order that created it, so
you can always trace a setup task to the exhibitor and line that asked
for it.

## Completion comes back to the summary

The exhibitor detail page shows a **fulfillment rollup** - an "N of M
complete" view of the work orders generated for that exhibitor -
alongside booth and payment information. That gives you the whole
picture of one exhibitor in one place: what they ordered, what they
owe, and whether the floor is ready for them.

When a work order is marked complete, two things follow automatically:

- The exhibitor's fulfillment rollup advances, and
- Equipment **inventory updates** to reflect what's now deployed on the
  floor - for any line whose SKU matches a tracked stock item at that
  venue, its available count draws down. Lines with no matching stock
  record are still itemized; they just move no inventory.

## Edits and cancellations stay in sync

Orders change, and fulfillment follows. If an exhibitor adds or removes
items, the linked work orders **reconcile** - quantities update in
place rather than spawning duplicates, and a crew's manual tweaks to a
generated item (notes, action) survive the edit. **Cancelling** an order
cancels its outstanding work orders, so a withdrawn exhibitor never
leaves orphaned setup tasks on a crew's list.

Once a department's setup work order is marked **complete**, that
department is locked on the order - you can't quietly change what the
floor already built. Editing or removing those lines is blocked; make
the change with a manual work order instead.

## When generation happens

By default, work orders generate the moment an order is **confirmed** -
the floor preps regardless of whether payment has landed yet. A venue
that prefers to hold fulfillment until an order is paid can change the
trigger in the event's settings; the default is confirm-on-order.

## Where to go next

- [Orders](/docs/exhibitors/orders) - placing + managing the order itself
- [Exhibitors overview](/docs/exhibitors/overview) - booth, balance, contacts
- [Work orders](/docs/operations/work-orders) - the crew-side view of every task
