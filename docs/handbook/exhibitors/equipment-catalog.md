---
title: Equipment catalog
section: Exhibitors
order: 58
---

The equipment catalog is the venue-scoped list of items exhibitors
can rent - chairs, tables, tents, generators, A/V kits, drape, etc.
It's the master list of rentable items used to populate exhibitor
orders, with department + financial category mapping for ops routing
and journal entries.

## Structure

| Field | Purpose |
| --- | --- |
| Venue | Which venue stocks this item |
| SKU | Stable identifier (e.g. `CHAIR-FOLD`, `TABLE-6FT`) |
| Name | Human label exhibitors see |
| Category | `chair`, `table`, `tent`, `av`, `staging`, etc. |
| Department | Internal ownership (Ops, A/V, F&B...) - drives work-order routing |
| Financial category | Maps to a revenue account on the CoA |
| Rate (per unit) | Cents-denominated price; rate snapshots preserve into orders |
| Inventory total | How many the venue owns |
| Inventory available | Computed from outstanding orders in the window |

## Default seed

The application ships with a representative starter catalog -
folding chairs, 6-ft and 8-ft tables, basic A/V (projector, screen,
mic kit), and tent options. The starter set is intentionally small;
real-world catalogs grow into the hundreds of items per venue.

## How rates flow into orders

When an exhibitor adds a catalog item to an order, the order line
snapshots the catalog's current `rate_cents`. Updating the catalog
later doesn't change rates on already-issued orders - that's by
design so price corrections don't retroactively rewrite past
invoices.

## Inventory limits

Today inventory is **advisory** - the system tells you how many of an
item are available given outstanding orders in the date window but
doesn't refuse over-bookings. A hard inventory check is on the
roadmap once we have a real customer running into the constraint.

## What's not in the catalog yet

- Items with non-uniform pricing (tiered: 1-10 chairs at $X, 11+ at
  $Y) - today everything is flat-rate
- Bundled "kits" that sub-decompose at delivery time
- Service items (labor, setup fees) - these exist informally as
  line items but don't have a catalog representation
