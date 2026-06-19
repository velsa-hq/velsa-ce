---
title: Departments
section: Admin
order: 65
surfaces:
  - route: /admin/departments
    method: GET
  - route: /admin/departments
    method: POST
  - route: /admin/departments/{department}
    method: PUT
  - route: /admin/departments/{department}
    method: DELETE
  - component: admin/departments/index
tour_ids:
  - department-label
  - department-add
  - department-hide
  - department-move-up
  - department-move-down
---

**Departments** are the operations teams that run-of-show outline items
are bucketed into - Setup, A/V, Catering, Security, and so on. The list
is **user-definable**: admins manage it at `/admin/departments` instead
of it being hardcoded.

It feeds four places:

- the **Department** picker when editing a [run-of-show](/docs/operations/run-of-show)
  outline item
- the columns + **Department** filter on the [Ops board](/docs/operations/ops-board)
- the **Department** filter on the service-schedule report
- the **default crew role** used to auto-assign generated work orders
  (below)

## The list

Each department has:

- a **label** - the human name (e.g. "A/V")
- a **key** - an auto-generated slug stored on outline items (e.g.
  `av`); immutable once created so existing items keep their bucket
- a **color** - the chip color used on the Ops board, picked from a
  fixed palette
- an **order** - controls column order on the board + dropdown order
  (set with the up/down arrows)
- a **shown/hidden** state - hidden departments stay on existing items
  but drop out of the pickers, board columns, and report filters
- a **default crew role** (optional) - the role that work orders for
  this department auto-assign to (see below)
- an **in-use** count - how many outline items currently use it
- a **system** badge on the seeded defaults

## Adding a department

Type a label (e.g. "Medical"), pick a color, and click **Add
department**. The key is derived from the label (`medical`). If one with
the same key already exists, the add is rejected - pick a distinct name.

## Renaming + recoloring

Edit a row's label and/or color and click **Save**. The key can't be
changed (it's the value stored on items).

## Hiding (the easy way to curate the defaults)

Click **Hide** on any department to drop it from the outline picker, the
board columns, and report filters; the row dims and shows a "hidden"
badge. Hidden departments stay on any items already using them - nothing
is lost. This is the intended way for an org to trim defaults it doesn't
run (a venue with no parking hiding **Parking**, say). System
departments can be hidden too.

## Default crew role (auto-assigning work)

Set a department's **default crew role** to have generated work orders
for that department land on a real owner instead of an unassigned queue.
When the exhibitor-fulfillment generator (or a recurring template) cuts a
work order, it looks up the department's role and assigns the order to a
user who holds that role - preferring one assigned at the work order's
venue, falling back to any holder. Leave it blank to keep generated work
unassigned (a coordinator picks it up). Only new work orders are
auto-assigned; a crew's manual reassignment is never overwritten.

## Reordering

Use the **↑ / ↓** arrows to move a department up or down; the order is
the column order on the board and the option order in pickers.

## Deleting

A department can only be deleted when it's **not a system department**
and **not in use** by any outline item. Otherwise the Delete control
shows a dash:

- **System departments** (the seeded defaults) can't be deleted -
  **Hide** one instead.
- A department **in use** must have its items reassigned first.

## Why a managed list (vs. a fixed enum)

Operations structure differs by organization - a county fair runs a
livestock team, a convention center runs a loading-dock crew. Making the
taxonomy editable lets each deployment shape its ops teams without a code
change, while the `is_system` defaults guarantee a stable baseline for
seed data and reporting. Mirrors how [Space kinds](/docs/admin/space-kinds)
work.
