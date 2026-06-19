---
title: Inventory
section: Operations
order: 44
surfaces:
  - route: /inventory
    method: GET
  - route: /inventory/activity
    method: GET
  - route: /inventory/print
    method: GET
  - route: /inventory
    method: POST
  - route: /inventory/{resourceInventory}
    method: PATCH
  - route: /inventory/{resourceInventory}
    method: DELETE
  - component: inventory/index
  - component: inventory/activity
tour_ids:
  - inventory-add
  - inventory-low-filter
  - inventory-print
---

:::video inventory

Inventory (`/inventory`, or **Operations -> Inventory**) is the
deployable-equipment catalog - chairs, tables, linens, AV, staging,
power, and so on. Resources are **per venue**: each row belongs to one
venue and tracks how many you own (**total**) and how many aren't
currently committed (**available**).

Work-order items draw against these resources, so inventory is the
source of truth the ops side reconciles against.

## What you see

- **Resource** - the name and an optional **SKU** (unique within a
  venue).
- **Kind** - a grouping (chairs, av, staging, ...) chosen from a
  user-definable list managed at **Admin -> Inventory kinds**.
- **Venue** - the owning venue.
- **Available / Total** - committed-aware stock. Available hitting **0**
  is flagged in red.
- **Consumable vs durable** - durable assets (chairs, generators, AV...)
  are deployed and returned, so they never "reorder". Mark an item
  **Consumable** (it gets used up) to enable a reorder point.
- **Reorder point** - *consumables only*: when available drops to or
  below it, the row shows a **Reorder** badge (0 = no alert).

## Replenishment

The **Low stock** toggle (with a count) filters to **consumables** at or
below their reorder point - your replenishment worklist. Durable assets
are never flagged, even when most of them are out on deployment. Pair it
with the venue filter to see what one facility needs to reorder.

## Use activity

**Activity** (top of the page) opens the use-activity report - every
stock movement applied by a completed work order (deploy / return /
consume / replace), newest first, linked back to its work order.
Filterable by venue.

## Filtering

The **venue** and **type** (consumable / durable) dropdowns narrow the
list; the **Low stock** toggle limits to consumables needing reorder.
All filter choices are reflected in the URL so a view is shareable.

## Count sheets

**Print sheet** generates a printable **count sheet** - the system
on-hand for each resource plus blank **Counted** and **Notes** columns,
grouped by venue, with a counted-by/date line. Hand it to someone to
walk the stock room. It respects the current venue / type / low-stock
filters, so you can print "just the consumables for Pelican Cove." After
counting, edit each resource to reconcile the numbers.

## Adding + managing

- **+ Add resource** opens a dialog for the name, venue, kind, optional
  SKU, total quantity, and how many are available now (can't exceed
  total).
- **Edit** any row to adjust counts or details in the same dialog.
- **Retire** removes a resource from the active list. It's a soft
  delete - historical work-order items that referenced it keep their
  reference; the resource just stops appearing here and in pickers.
  Retiring is **blocked while stock is still applied** to open work
  orders (there'd be nothing to return it to) - complete or reverse
  those first.

## See also

- [Work orders](/docs/operations/work-orders) - where resources are
  drawn down (and returned) by work-order items.
