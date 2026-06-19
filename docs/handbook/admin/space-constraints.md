---
title: Space constraints (floor plan)
section: Admin
order: 79
surfaces:
  - route: /admin/spaces/{space}/constraints
    method: GET
  - route: /admin/spaces/{space}/constraints
    method: POST
  - component: admin/spaces/constraints
tour_ids:
  - admin-sc-add-wall
  - admin-sc-add-column
  - admin-sc-add-door
  - admin-sc-add-outlet
  - admin-sc-properties
  - admin-sc-save
---

:::video space-constraints

**Space constraints** are the permanent infrastructure inside a
space - walls, doors, windows, columns, outlets, posts. They don't
change between events, so they live at the Space level (not on a
per-booking diagram). The booking-level floor plan editor renders
them as a non-draggable backdrop on every event held in the space,
and the auto-layout feature uses them as ground truth for usable
area (it places tables avoiding walls and columns).

Lives at `/admin/spaces/{space}/constraints`. Reached from the
venue show page at `/venues/{venue}` - each space card has a small
**Floor plan** link in the corner.

## When to set them up

Once per space, when the venue first comes online. After that, only
edit when the building actually changes (knocked out a wall, added
an outlet, installed a new column). Bookings in progress pick up
the updated backdrop on next render.

## Editor

The editor uses the same Konva engine as the booking-level floor
plan editor, but the **Constraint library** on the left only
contains infrastructure types. Each palette button is labelled
by kind + a size hint:

| Palette button | Default size (W × H) | Use for |
| --- | --- | --- |
| **Wall (10 ft)** | 10 ft × 0.5 ft | Perimeter walls, interior partitions |
| **Door (3 ft)** | 3 ft × 0.5 ft | Single + double doors, emergency exits |
| **Window (6 ft)** | 6 ft × 0.4 ft | Window banks |
| **Column 2×2** | 2 ft × 2 ft | Structural columns |
| **Post 1×1** | 1 ft × 1 ft | Smaller structural posts, bollards |
| **Outlet** | 0.6 ft circle | Power outlets (esp. AV-critical ones) |

Click a palette item to add one at the center of the canvas; drag
to position it on the grid; click to select; **Del** or the Delete
button to remove. The **Properties** panel on the side lets you
resize, rotate, and label any selected item.

A typical convention center setup runs ~10-15 features: four
perimeter walls (each as one Wall rotated appropriately), a few
interior columns on the load-bearing grid, double doors at the
lobby entrance, and a couple of outlets along the AV wall. Don't
over-model - features the floor plan can't act on aren't worth
adding.

## Save

The button in the header persists the entire set as a JSON blob
on the Space row. The label toggles between **Save** (when there
are unsaved changes) and **Saved** (when the canvas is clean), so
you can see at a glance whether anything's pending. There's no
version history at the constraint level; if you make a mistake,
undo by editing and re-saving.

## How constraints show up downstream

- **Booking floor plan editor** (`/bookings/{id}/diagram`) renders
  the constraint set as a read-only backdrop layer below the
  event objects. Walls look dark-slate, doors amber, windows sky,
  columns mid-slate, outlets a yellow dot.
- **Auto-layout from headcount** in the floor plan editor walks
  every candidate grid slot and skips any that would overlap a
  constraint's axis-aligned bounding box. The result honors the
  room's permanent infrastructure; the partial-fit banner surfaces
  when constraints block more tables than would otherwise fit.

## Seeded demo data

On a fresh `migrate:fresh --seed`, four spaces ship with realistic
constraint sets:

- **Coral Reef Grand Ballroom + Exhibit Hall A** - 4 perimeter
  walls + 4 interior columns + double lobby doors + 2 east-wall
  outlets (12 features)
- **Main Theater** (Aquila Performing Arts Hall) - stage apron +
  side walls + 2 emergency exits (5)
- **Sentinel Bay Arena** - bench dividers + press outlet (3)
- **Driftwood Indoor Expo Hall** - 6 columns + rolling load-in
  door (7)

Useful when watching how the booking diagram renders against a
realistic backdrop.

## See also

- [Floor plan editor](/docs/diagrams) - where constraints are
  rendered, and where auto-layout honors them
- [Layout templates](/docs/admin/layout-templates) - saved
  arrangements that drop on top of the constraint backdrop
