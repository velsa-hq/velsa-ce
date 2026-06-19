---
title: Bookings overview
section: Bookings
order: 10
surfaces:
  - route: /bookings/{id}
    method: GET
  - route: /bookings/{id}/clone
    method: POST
  - component: pages/bookings/show
  - component: components/bookings/booking-form
tour_ids:
  - bk-rebook-clone
  - bk-edit-btn
  - bf-event-name
  - bf-venue
  - bf-client
  - bf-event-kind
  - bf-when-range
  - bf-save-booking
---

A **booking** is a single event held at one of your venues. Every
booking has a venue, a client, a kind (wedding, conference, trade show,
etc.), a time window, and at least one space inside the venue that it
occupies. The reference (e.g. `BK-2026-A4F7Z`) is generated automatically
on save.

:::video booking-clone

## Status lifecycle

Bookings move through six states. The status drives both visibility on
the calendar and whether the booking blocks other bookings on the same
space.

| Status | Blocks space? | Typical meaning |
| --- | --- | --- |
| Inquiry | No | A lead asked about a date; nothing committed |
| Hold | No | Soft hold while we work it (1st, 2nd, 3rd holds may co-exist) |
| Tentative | No | Contract drafted but not signed yet |
| Definite | **Yes** | Contract signed; space is locked |
| Completed | **Yes** | Event has happened |
| Cancelled | No | Client backed out or we declined |

Status transitions are free-form in the form (any status -> any status),
but the **definite/completed** statuses cannot be set if the space is
already locked by a different booking in the same window. See
[Space overlap rules](/docs/bookings/overlap-rules) for the exact rule.

## Where bookings appear

- **Bookings index** (`/bookings`) - list view, filterable by venue and status, with chip-style status summary at the top
- **Booking detail** (`/bookings/{id}`) - single-booking view with all facts, attached spaces, contracts, and run-of-show summary; entry point to Edit / Floor plan / Run-of-show
- **Dashboard** - upcoming bookings and revenue charts pull from this same data
- **Calendar / Ops board** - surfaces bookings that fall in the next two weeks

## Re-book from an existing event

The booking detail page has a **Re-book (clone)** action that copies an event's
details - client, venue, type, attendance, pricing - into a fresh **inquiry**.
Space placements aren't carried over (the original still holds its slots, and
the system forbids double-booking), so you pick the new dates and add spaces on
the cloned inquiry. Handy for recurring or annual events.
