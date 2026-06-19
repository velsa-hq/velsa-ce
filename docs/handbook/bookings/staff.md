---
title: Staff roster
section: Bookings
order: 35
surfaces:
  - route: /bookings/{booking}/staff
    method: POST
  - route: /staff-assignments/{assignment}
    method: DELETE
  - component: bookings/show
tour_ids:
  - booking-staff-add
  - booking-staff-person
  - booking-staff-role
  - booking-staff-shift
  - booking-staff-rate
  - booking-staff-remove
---

:::video assign-booking-staff

The **Staff** card on the booking detail page is the per-event
roster - who's working the event, in what role, during which shift,
at what hourly rate. It answers the question of "which Venue
employee is assigned to the event" with enough granularity to cover
split shifts and multi-day events.

Lives on the booking show page at `/bookings/{booking}`, below the
outline. POST to `/bookings/{booking}/staff` to add an assignment;
DELETE to `/staff-assignments/{assignment}` to remove one.

## What a staff assignment captures

Each row is one shift for one person:

| Field | Notes |
| --- | --- |
| **Person** | Picked from the full user list - only existing app users can be rostered. The outline editor's responsible-user dropdown then reads *from this roster*, so adding someone here is what makes them assignable to outline items |
| **Role** | Free-text label - "Event lead", "A/V tech", "Setup crew", "Security", etc. Convention is title-case; no fixed enum |
| **Shift** | `start_at` -> `end_at` datetime range. Defaults to the booking's start/end on open but can be narrowed for partial coverage |
| **Hourly rate** | Defaults to $35.00/hr. Captured per-assignment, not per-user, so the same person can be rostered at a different rate on a different event |
| **Notes** | Optional - anything the next person reading the booking should know about this shift |

The card header rolls up the totals: `N assignment(s) · X.X hrs ·
$Y est. labor`.

## Adding an assignment

1. Click **Add assignment** in the card header
2. Pick the **Person** from the dropdown - only users already in
   the system appear; if the right person isn't there, an admin
   needs to create the user first
3. Type the **Role**
4. Adjust the **Shift** start/end if it differs from the full
   event window
5. Set the **Rate ($/hr)** (defaults to $35.00/hr)
6. Optional **Notes**
7. **Add** - the row appears in the table and the rolled-up
   totals update

A person can hold multiple assignments on the same booking. That's
the right shape for:

- **Split shifts** (morning setup + evening teardown for the same
  person) - two rows, each with its own hourly range
- **Multi-day events** with daily shifts - one row per day
- **Role changes** mid-event (someone setting up as crew then
  switching to event lead) - two rows with different roles

## Removing an assignment

The **Remove** button at the end of each row deletes the row after
a confirmation. Removal is hard delete - staff assignments don't
soft-delete, since the audit log captures the change and a
re-add is cheap. If the deleted person was the responsible user on
any outline items, those items keep their attribution; the outline
editor just won't offer that person as a candidate on new edits
until they're rostered again.

## Why this matters downstream

The roster isn't just record-keeping - it feeds the outline
editor's **responsible user** dropdown. When you pick who owns an
outline item (a setup task, a coffee break, a tear-down step), the
dropdown's candidate list is exactly the people rostered on this
booking. You can't assign an item to someone who isn't on the
floor.

This is intentional and removes the most common day-of confusion:
"who's actually here to do this?" If the dropdown is empty, the
roster is empty - fill it first, then go back to the outline.

## Labor cost rollup

`Est. labor` in the header is the sum of `(duration_hours ×
hourly_rate)` across every assignment. It's an *estimate* - there's
no clock-in/clock-out yet, so it's the planned cost, not the
actual. Useful for:

- Quoting an event when staffing dominates the cost
- Spotting a roster that's badly over- or under-staffed at a glance

It does **not** flow into the booking total or any invoice. It's a
sales/ops-side number for now; if the County wants planned labor
posted to the GL alongside fees, that's a future feature.

## See also

- [Event outline](/docs/operations/run-of-show) - where rostered
  staff are picked as the responsible user on individual items
- [Editing a booking](/docs/bookings/editing-a-booking) - adjacent
  panel on the same page
- [Event narrative](/docs/bookings/event-narrative) - capture
  context about staffing decisions ("had to swap leads at the last
  minute because X")
