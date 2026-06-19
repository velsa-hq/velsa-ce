---
title: Calendar
section: Operations
order: 43
---

:::video ops-overview

The calendar at `/ops/calendar` (or **Operations -> Calendar** in the
sidebar) is the venue-centric, month / week / day view of every
booking and blackout in the system. Companion to the
[two-week schedule](/docs/operations/schedule):

- **Schedule** is *space-day* - "is the Ballroom free on the 15th?"
- **Calendar** is *venue-month* - "what's happening at the Civic
  Center this November?"

Both are kept because the mental models are genuinely different;
neither replaces the other.

## What you see

Each booking renders as a colored event pill keyed off its status:

| Status | Colour |
| --- | --- |
| Hold | amber |
| Tentative | sky |
| Definite | emerald |
| Completed | violet |
| Cancelled / Inquiry | hidden (filtered out of the feed) |

Click any event to jump to the booking detail page.

**Blackouts** render as **translucent rose-tinted background
overlays** across the days they cover. They sit *behind* booking
events so you can see at a glance which windows are off-limits
without losing the bookings that happen to land in those windows. The
overlay carries the blackout reason as its label.

## Views

The toolbar in the calendar's top-right cycles between four views:

- **Month** - the default; full-month grid with stacked events per day
- **Week** - 7 days × time-grid, useful for back-to-back loadout / event / breakdown
- **Day** - single-day time-grid, useful when you need every minute laid out
- **List** - agenda-style list for emailing / scanning, no grid

The top-left has **Prev / Next / Today** for navigation. The view
respects the current venue filter.

## Filtering

Two filters:

- **Venue** - the dropdown at the top narrows everything to one
  venue's bookings + blackouts. Leave it on "All venues" for the master
  view. (This reloads the feed from the server.)
- **Status** - the **legend doubles as a status filter**. Click a
  status chip (Hold, Tentative, Definite, Completed, Blackout) to show
  only that status, click more to add them, and **Clear** to return to
  everything. It filters the events already on screen, so it's instant
  and resets when you leave the page; with nothing selected, all
  statuses show. The header label switches from "Filter by status" to
  "Showing" while a filter is active.

## What's not (yet) draggable

The calendar is read-only today. Drag-to-reschedule and resize-to-
extend are queued - they require an editable backend update endpoint
with conflict checking against the same booking-overlap rules used
on save. For now, to move or extend a booking, click through to the
booking detail page and edit it from there.
