---
title: Ops board
section: Operations
order: 40
surfaces:
  - route: /ops/board
    method: GET
  - component: ops/board
---

:::video ops-overview

The Ops board (`/ops/board`) is a 2-week look-ahead grouped by date
and department. It's the one screen operations leads should keep
open during a busy week.

## What it shows

Every **outline item** from a **published** outline scheduled to
happen in the window - that's the run-of-show entries for upcoming
bookings, broken out by department. Each row is one event for one
day; each column is one department.

Departments are colored chips, one per row in the grid - the active
departments configured under [Admin -> Departments](/docs/admin/departments),
each with its own color and order. The seeded defaults are Setup, A/V,
Catering, Security, Cleaning, Parking, Reception, Teardown, and Ops lead,
but admins can add, rename, recolor, reorder, or hide them.

The default window is **14 days** starting today. You can stretch
it with `?days=N` in the URL, clamped to a 7-28 day range.

## Working from a tile

Each item tile shows its time + duration, the activity title, and a
link to the parent **booking** (click the booking name to jump
straight to it). When an item has a [checklist](/docs/operations/run-of-show#checklists),
the tile also shows a **done / total** badge (e.g. `☑ 2/4`) so you
can read progress at a glance.

Clicking the **time/title** opens the same **item dialog** used on
the run-of-show page - edit the time, duration, department, or
description, and tick the checklist - without leaving the board. The
dialog header links back to the booking and shows the venue and
space. Times display as the venue wall-clock, not the viewer's
timezone.

## Published-only gate

The board **only shows items from published outlines** - draft
outlines stay hidden so half-built run-of-shows don't show up to
day-of ops. This is enforced at the controller via a
`whereNotNull('published_at')` filter (covered by a Pest test that
asserts the gate is honored). If a booking you expect to see is
missing, the most common cause is that the outline hasn't been
published yet. Open the run-of-show page and click **Publish
outline**.

## Filters

Three filters across the top:

- **Venue** - narrow to one of the active venues
- **Department** - show only one department's items (e.g.
  Catering)
- **Days** - stretch or shrink the window (7-28)

Filters compose. The URL reflects them so a filtered view is
shareable.

## Where items come from

Items on the ops board are `outline_items` rows on a published
`event_outline`. Each outline is attached to a booking; each item
is one timed line in that booking's run-of-show. The seeder
provides a 13-item base template per booking plus 2-4 kind-specific
items (so a wedding gets a ceremony rehearsal slot, a trade show
gets a booth load-in window) - see
[Run-of-show](/docs/operations/run-of-show) for the full template.

To get something on the board:

1. Write or accept the booking's seeded run-of-show.
2. Click **Publish outline** on the run-of-show page (this is the
   gate).
3. The item appears as soon as the booking's start date falls
   inside the configured window.

## Total items / by-department summary

The header shows the total item count for the window. A zero in
any department isn't necessarily wrong - it just means no outline
item was scheduled in that bucket for that window. Use it as a
sanity check: if a busy week shows zero Setup items, the outline
is probably still in draft.

## See also

- [Run-of-show outlines](/docs/operations/run-of-show) - where
  outlines are authored + published
- [Staff roster](/docs/bookings/staff) - the responsible-user
  candidate pool that feeds the per-item ownership column
- [Calendar](/docs/operations/calendar) - booking-level view if you
  want a different slice
