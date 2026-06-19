---
title: Two-week schedule
section: Operations
order: 41
---

:::video ops-overview

The two-week schedule at `/ops/schedule` (or **Operations ->
Schedule** in the sidebar) is a per-space calendar grid: every
selected space is a row, the next 14 days are columns, and bookings
+ blackouts render as horizontal bars across the cells they occupy.

It's the location-sliced companion to the
[Ops board](/docs/operations/ops-board) - that view groups by
department, this one groups by space. Use this when you need to
answer "what's happening in Ballroom A over the next two weeks?" at
a glance.

## What you see

- **Rows** - every active space, **grouped by venue under a colored
  divider band**. Each venue gets a stable accent color, and the band
  links to the venue page. Within a venue, spaces sort by name and each
  row shows the space name with its kind + capacity. Retired spaces
  don't appear.
- **Columns** - 14 days starting from today (or the date in `?from`).
  Weekends are tinted, today is highlighted.
- **Booking bars** - color-coded by status:
  - Amber: Hold
  - Sky: Tentative
  - Emerald: Definite
  - Purple: Completed
  - Cancelled and Inquiry-status bookings are excluded entirely
- **Blackout bars** - diagonal-striped gray bars with the reason
  (e.g. "HVAC maintenance"). Show on both space-level and venue-level
  blackouts. The label notes the scope (`venue` blackouts apply to
  every space in the venue).
- **Clickable** - clicking a booking bar opens the booking detail
  page; clicking a venue name in its divider band opens the venue page.
- **Pinned while scrolling** - the grid scrolls inside its own pane:
  the date header stays fixed at the top and the space column stays
  fixed on the left, so you keep your bearings in a long list or a wide
  window.

## Filters + navigation

The toolbar above the grid handles:

- **Prev week / Next week** - move the 14-day window backward or
  forward by 7 days
- **Today** - snap back to today
- **Venue dropdown** - restrict to one venue, or show all venues at
  once
- The active window dates show on the right ("Sep 1 - Sep 14")

The current window state is in the URL (`?from=YYYY-MM-DD&venue_id=N`)
so a filtered view is shareable.

## How bookings span cells

A booking that starts before the window or ends after it gets
**clamped** to the visible range - the bar runs to the edge of the
grid and the booking detail page tells you the full window.

Multi-day bookings render as a single bar spanning the days they
occupy. A booking that ends exactly at midnight (00:00) is treated as
ending on the previous day - the booking covers the prior day, not
the new one starting at 00:00.

## What's not on this view (yet)

- **Drag-and-drop** to move a booking between days / spaces - every
  edit still goes through the booking detail page
- **Partition tree blackouts** - a blackout on a partition parent
  (e.g. Grand Ballroom) blocks bookings on its children at save time,
  but the schedule view currently only shows direct blackouts on the
  child space + venue-wide blackouts. Future enhancement.
- **Per-space filter** - today you can narrow by venue but not pick
  individual spaces. Quick add when needed.
- **Density / zoom controls** - fixed 14-day window. A 7-day or 4-week
  toggle would be a natural addition.
