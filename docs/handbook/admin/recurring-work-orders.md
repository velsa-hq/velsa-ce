---
title: Recurring work orders
section: Admin
order: 67
surfaces:
  - route: /admin/work-order-templates
    method: GET
  - route: /admin/work-order-templates
    method: POST
  - route: /admin/work-order-templates/{workOrderTemplate}
    method: PUT
  - route: /admin/work-order-templates/{workOrderTemplate}
    method: DELETE
  - component: admin/work-order-templates/index
tour_ids:
  - wot-add
  - wot-name
---

:::video recurring-work-orders

Recurring work orders (**Admin -> Recurring work orders**) are the
**preventive-maintenance templates** that auto-generate real work orders
on a schedule - weekly filter checks, monthly inspections, quarterly
deep cleans.

## What a template defines

- **Name, venue, kind** - what the generated order is and where.
- **Cadence** - set with structured fields (repeat **weekly** or
  **monthly**, an **interval**, a weekday or day-of-month, and an hour);
  the app composes the underlying schedule rule so you never type iCal.
  The list shows a plain-English cadence (e.g. *every 2 weeks on Wed at
  9am*).
- **Look-ahead days** - how far in advance orders are materialized.
- **Default assignee role** - optional role to route generated orders to.
- **Materials** - the item list each generated order gets (name, qty,
  unit, action), mirroring a work order's items.
- **Active** - only active templates generate; deactivate to pause one
  without deleting it + its history.

## How generation works

A nightly job (`workorders:materialize`) walks each active template and
creates real work orders across its look-ahead window, then advances the
cadence for the next run - so preventive maintenance appears on the
[ops board](/docs/operations/ops-board) and
[work-orders list](/docs/operations/work-orders) automatically. Each
generated order links back to its template on the detail page, and the
template row shows how many it has generated.

## Managing

**+ Add template** opens a dialog; click a template (or **Edit**) to
change it; **Delete** removes the template (existing generated orders are
untouched). Edits apply to *future* generations only.
