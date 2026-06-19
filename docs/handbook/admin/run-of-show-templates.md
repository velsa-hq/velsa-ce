---
title: Run-of-show templates
section: Admin
order: 66
surfaces:
  - route: /admin/outline-item-templates
    method: GET
  - route: /admin/outline-item-templates
    method: POST
  - route: /admin/outline-item-templates/{outlineItemTemplate}
    method: PUT
  - route: /admin/outline-item-templates/{outlineItemTemplate}/toggle
    method: PATCH
  - route: /admin/outline-item-templates/{outlineItemTemplate}
    method: DELETE
  - component: admin/outline-item-templates/index
tour_ids:
  - template-label
  - template-checklist
  - template-add
---

:::video manage-run-of-show-templates

**Run-of-show templates** are reusable outline items - a repeatable
activity (an A/V sound check, a catering load-in) bundled with a
default duration, department, Markdown description, and checklist.
Staff drop them onto a booking's run-of-show from the **Start from a
template** picker (see
[Run-of-show outlines](/docs/operations/run-of-show#start-from-a-template)),
where they prefill the item - so common activities are one click
instead of re-typed every event.

Manage them at **Admin -> Run-of-show templates**
(`/admin/outline-item-templates`).

## What a template carries

- **Label** - the activity name, used as the item title ("A/V sound
  check")
- **Department** - the ops team it belongs to, from the
  [configured departments](/docs/admin/departments)
- **Default duration** - minutes (5-1440), prefilled on the item
- **Description** - optional, supports **Markdown** (bold, lists,
  links); renders formatted on the item and the run sheet
- **Checklist** - one step per line; seeds the new item's tickable
  checklist

## Adding a template

Fill in the **Add a template** card - label, department, duration,
an optional Markdown description, and a checklist (one item per
line) - then **Add template**. It's immediately available in the
picker on every booking's run-of-show.

## Editing, hiding, deleting

Each template is an inline-editable row - change any field and
**Save**.

- **Hide / Show** toggles whether the template appears in the
  picker, without deleting it. Hidden templates keep their history
  and can be brought back any time.
- **Delete** removes a template - but only **custom** ones.

## System templates

The seeded defaults - **A/V sound check**, **Crew setup**,
**Catering load-in**, **Pre-event ops huddle**, and **Teardown** -
are marked **system**. They can be hidden, edited, and reordered,
but **not deleted** (the seeder is their source of truth). To retire
one, hide it.

Templates are independent of the items created from them: editing or
deleting a template never changes outline items already placed on a
booking.

## See also

- [Run-of-show outlines](/docs/operations/run-of-show) - where
  templates are applied to a booking
- [Departments](/docs/admin/departments) - the department list a
  template draws from
