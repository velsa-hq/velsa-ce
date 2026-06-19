---
title: Blackouts
section: Venues
order: 6
---

A **blackout** is an explicit window when a venue or a specific space
inside it is unavailable for booking - HVAC maintenance, carpet
replacement, annual deep clean, a county training event, anything that
takes the space off the market.

Today bookings only block each other; a blackout adds a second blocker
that the booking-overlap check respects.

## Where you set them

`/venues/{slug}` -> **Blackouts** card. The card shows every active and
upcoming blackout for the venue (plus any on its spaces) and has an
**Add blackout** button.

## Adding a blackout

1. Click **Add blackout** on the venue page
2. **Scope** - pick `Entire venue` or a specific space within the venue
3. **Starts** + **Ends** - date+time pickers (half-open semantics: a
   booking starting exactly at `ends_at` is allowed)
4. **Reason** - short human-readable text (shown in the booking-conflict
   error and on the calendar). E.g. `HVAC maintenance`, `Carpet
   replacement`, `Health-department closure`
5. **Submit**

## How the conflict check works

When a booking tries to attach to a space, the overlap check walks
upward through:

- The space itself
- The space's parent (if it's a partition section like
  `Section A` under `Grand Ballroom`)
- That parent's parent (and so on, all the way up)
- The owning venue

If any blackout on that chain overlaps the booking's time window,
the save is rejected with a message like:

> Space is unavailable from 2026-08-04T00:00:00+00:00 to
> 2026-08-06T23:59:00+00:00: HVAC maintenance.

This means:

- A blackout on a partitioned parent space (e.g. a Grand Ballroom)
  blocks all of its child sections automatically
- A blackout on a venue blocks every space in it
- A blackout on one child section does **not** block its siblings -
  siblings are independent

## Removing a blackout

From the venue page, click **Remove** on the row. Confirms with a
prompt, deletes the row, fires a `model.deleted` audit. Removing a
blackout doesn't retroactively un-block any bookings that were
already prevented while it was active - they simply weren't created.

## What's not wired yet

- **Calendar visualization** - the ops board doesn't yet visually
  mark blackout windows. You see them on the venue page and at
  booking-save time only.
- **Recurring blackouts** - every blackout is a one-shot today.
  "Every Sunday for cleaning" would need a recurrence model.
- **Partial-day windows on the calendar** - works correctly in the
  conflict check but the visual rendering is whole-day blocks.
