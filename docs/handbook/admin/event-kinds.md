---
title: Event kinds
section: Admin
order: 66
surfaces:
  - route: /admin/event-kinds
    method: GET
  - route: /admin/event-kinds
    method: POST
  - route: /admin/event-kinds/{eventKind}
    method: PUT
  - route: /admin/event-kinds/{eventKind}
    method: DELETE
  - component: admin/event-kinds/index
tour_ids:
  - event-kind-label
  - event-kind-add
  - event-kind-hide
  - event-kind-move-up
  - event-kind-move-down
---

**Event kinds** are the taxonomy used to classify bookings - wedding,
conference, expo, and so on. The list is **user-definable**: admins
manage it at `/admin/event-kinds` instead of it being hardcoded. It
drives the **Event kind** dropdown when [creating a booking](/docs/bookings/creating-a-booking).

This works exactly like [Space kinds](/docs/admin/space-kinds) and
[Departments](/docs/admin/departments): add a kind (the key is derived
from the label), rename it, reorder it with the ↑/↓ arrows, **Hide** it
to drop it from the picker without touching existing bookings, and
delete it only when it's not a system default and not in use by any
booking. The seeded defaults are marked **system** and can't be deleted
- hide them instead.

## A shared pattern

Event kinds, space kinds, and departments are all the same kind of
thing - a small **user-definable lookup list** the org curates in-app.
They share one implementation (a taxonomy model trait + a generic admin
screen), so new managed lists added later behave identically: add /
rename / reorder / hide, system-protected defaults, and an in-use guard
before deletion.
