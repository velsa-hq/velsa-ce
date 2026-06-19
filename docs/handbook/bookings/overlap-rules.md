---
title: Space overlap rules
section: Bookings
order: 13
---

Two bookings can sit on the same space at the same time - sometimes.
The system enforces the rules below at save time; if a save would
violate them, it fails with a `spaces` error and the booking isn't
created (or updated).

## The rule, in one sentence

A booking in **Definite** or **Completed** status cannot share a space
and time window with any other booking. Any other status (Inquiry, Hold,
Tentative, Cancelled) can co-exist freely.

## What that means in practice

- **Holds and tentatives stack.** Two clients can hold the same date at
  the same venue. This is intentional - overlapping 1st / 2nd / 3rd
  holds let multiple prospects be tracked without one arbitrarily
  winning.
- **Definite locks the space.** When a booking goes Definite (signed
  contract), it takes the space exclusively. Any other booking on the
  same space in an overlapping window must be cancelled or moved first.
- **Trying to go Definite on a contested space fails.** The save is
  rolled back and the form re-renders with an error showing which
  booking is blocking.

## What counts as "overlapping"

Times overlap when `start_a < end_b` **and** `end_a > start_b`. Equal
endpoints don't overlap - a booking ending at 4:00 PM and another
starting at 4:00 PM on the same space is fine.

## The check runs on save

Every space-on-booking change (create or update) runs the check.
That means all of these trigger it:

- Creating a new booking with Definite status
- Editing an existing booking's status to Definite or Completed
- Moving the start/end of an existing Definite booking
- Adding a space to a Definite booking
- Reassigning spaces on update

## When you hit a conflict

The error message names the booking that's blocking - e.g.
`Space is already booked by BK-2026-A4F7Z (definite) from ... to ...`.
Options:

1. **Move the conflicting booking** to a different time or space
2. **Cancel the conflicting booking** (Cancelled status releases the space)
3. **Don't go Definite yet** - leave the new booking as Tentative until
   the conflict is resolved

## Setup + teardown buffers (per-venue)

Each space-on-booking carries a **setup** time before and a **teardown**
time after the event. By default these are informational (they show on
the run sheet). A venue can opt to treat them as **occupied time** for
conflict detection: on the venue edit page, tick **Reserve
setup/teardown**.

When enabled, a booking's effective footprint for overlap purposes is
its event window **expanded by its own setup + teardown** - so two
bookings in the same space that sit back-to-back will conflict if one's
teardown or the other's setup overlaps. This reserves real turnaround
time. It's off by default, so existing venues see no change until you
turn it on.

## Backed by the database

Beyond the save-time check, the database itself enforces the core rule:
no two **Definite/Completed** bookings can overlap on the same space.
This is a hard guarantee - even two people confirming the last
contested slot at the same instant can't both win a race; the second
write is rejected. The application check gives the friendly,
explained error; the database constraint is the backstop that makes
double-booking impossible.

## Holds expire

A hold can carry an **expiration date** (`hold_expires_at`). A nightly
job releases holds whose date has passed: the hold is **cancelled**
(reason "Hold expired"), which frees the space, and the holds queued
behind it on that space + window **move up a position** - a 2nd-rank
hold becomes 1st, a 3rd becomes 2nd. Whoever moves into **first place**
is emailed that the space is now theirs to confirm. An unranked hold
expiring shifts no one.
