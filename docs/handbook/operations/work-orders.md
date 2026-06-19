---
title: Work orders
section: Operations
order: 41
surfaces:
  - route: /work-orders
    method: GET
  - route: /work-orders/create
    method: GET
  - route: /work-orders
    method: POST
  - route: /work-orders/{workOrder}
    method: GET
  - route: /work-orders/{workOrder}
    method: PATCH
  - route: /work-orders/{workOrder}/status
    method: PATCH
  - route: /work-orders/{workOrder}
    method: DELETE
  - route: /work-orders/{workOrder}/items
    method: POST
  - route: /work-order-items/{workOrderItem}
    method: PATCH
  - route: /work-order-items/{workOrderItem}
    method: DELETE
  - route: /work-orders/{workOrder}/print
    method: GET
  - route: /work-orders/print
    method: GET
  - component: work-orders/{index,show,create}
tour_ids:
  - wo-title
  - wo-venue
  - wo-kind
  - wo-submit
  - wo-new
  - wo-edit
  - wo-add-item
  - wo-print
  - wo-print-group
  - venue-new-work-order
---

Work orders track maintenance, setup, teardown, repairs, and other
one-off or recurring tasks against a venue. The index is at
`/work-orders`; filter by venue and status. Overdue work orders are
flagged at the top.

## Creating + viewing a work order

:::video work-orders

- **New work order** - the **+ New work order** button on the index
  opens a **dialog**; the same button on a venue page pre-selects that
  venue. Fill in a title, venue, kind, priority, optional schedule, and
  optional assignee. Picking an assignee starts the order in **Assigned**;
  otherwise it starts **Open**. A `WO-YYYY-XXXXX` reference is assigned
  automatically.
- **Detail page** - click any work order (from the index or a venue's
  **Active work orders** card) to open `/work-orders/{id}`: status,
  priority, schedule, assignee, requester, cost, linked booking, the
  originating template (for recurring orders), and its items.

## Managing a work order

From the detail page:

- **Status buttons** drive the lifecycle. While the order is open you'll
  see **Start** (-> In progress), **Complete**, and **Cancel**; once it's
  Completed or Cancelled, a **Reopen** button puts it back to Open.
  Completing stamps the completion time automatically; reopening clears
  it.
- **Edit** opens a dialog to change the title, venue, kind, priority,
  schedule, assignee, **cost**, and description, then save in place.
- **Delete** removes the order outright (behind a confirm) - use it for
  mistakes. To *stop* a real order while keeping the record, **Cancel**
  it instead.

## Printing / issuing

To **issue** work orders on paper, **Print**:

- On a work order's detail page, **Print** renders that one order as a
  PDF (with a completed-by / verified-by sign-off block).
- On the index, **tick the checkboxes** for the orders you want and
  **Print (N)** renders just those in one PDF. With nothing ticked the
  button reads **Print all (N)** and issues every order matching the
  current venue + status filters - so "issue all open orders for the
  Civic Center" is still one click.

## Recurring (preventive-maintenance) templates

Repeating work - weekly filter checks, monthly inspections - is defined
once as a **recurring template** at **Admin -> Recurring work orders**.
Each template has a cadence (e.g. *every 2 weeks on Wednesday at 9am*), a
look-ahead window, an optional default assignee role, and a materials
list. A nightly job (`workorders:materialize`) turns active templates
into **real work orders** across the look-ahead window and advances the
cadence for the next run - so preventive maintenance shows up on the
board automatically. Generated orders link back to their template on the
detail page.

## Items + inventory

A work order's **items** are the materials/equipment it touches. While
the order is open, **+ Add item** (and per-row **Edit** / **Remove**)
manage them; once it's Completed or Cancelled the list is read-only.

Each item has a **quantity**, a **unit**, and an **action**:

| Action | Inventory effect |
| --- | --- |
| Deploy | Moves stock **out** (available -) |
| Return | Brings stock **back in** (available +) |
| Consume | Permanently removes it (total - and available -) |
| Replace | Swap - no net change unless quantities differ |

Items can be **linked to a venue [inventory](/docs/operations/inventory)
resource** via the *From inventory* picker (which fills the name + SKU),
or left as free text. **When the order is Completed, linked items apply
their action to that venue's stock**; **reopening, cancelling, or
deleting a completed order reverses the deltas** and returns the stock.
The apply is idempotent - completing twice won't double-count.

## Kinds

| Kind | Used for |
| --- | --- |
| Setup | Event prep (chairs, tables, AV cabling) |
| Teardown | Post-event tear-down |
| Preventive maintenance | Scheduled checks (HVAC filters, fire-extinguisher inspections, deep cleans) |
| Repair | One-off fixes (leaky faucet, loose ceiling tile) |
| Event support | During-event labor needs |
| Inventory replenishment | Reorder consumables |
| Cleaning | Cleaning passes outside the regular cadence |

## Statuses

Draft -> Open -> Assigned -> In progress -> Completed (or Cancelled at any
step). Overdue is computed as `scheduled_for < now()` AND status is not
Completed/Cancelled - surfaced as a red OVERDUE tag on the row.

## Priority

1 (Critical) -> 2 (High) -> 3 (Normal) -> 4 (Low) -> 5 (Backlog). Use 1 for
anything that blocks an event or is a safety issue; 5 for nice-to-haves.

## Recurring templates

The repetitive maintenance work doesn't have to be filed by hand. A
**work-order template** describes a recurring schedule using the
standard iCalendar `RRULE` syntax:

- `FREQ=WEEKLY;BYDAY=MO;BYHOUR=8` - every Monday at 08:00
- `FREQ=MONTHLY;BYMONTHDAY=1` - first of every month
- `FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=15` - quarterly on the 15th

A nightly job materializes the next **14 days** of templates into real
work orders. Templates can also carry inventory consumption rules (e.g.
"each weekly HVAC check consumes 2 x 20×20 filters") which are recorded
on the materialized work order's items.

Seeded examples:

- Weekly HVAC filter check (every Monday, consumes 2 filters)
- Monthly fire-extinguisher inspection (1st of the month)
- Quarterly deep clean (every 3 months on the 15th)

## Cost tracking

Each work order has a `cost_cents` field (labor + materials) so the
ops totals on the dashboard and reports reflect actual spend by venue
and by kind.
