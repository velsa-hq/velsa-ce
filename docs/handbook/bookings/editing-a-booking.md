---
title: Editing & cancelling
section: Bookings
order: 12
---

From any booking's detail page, click **Edit** in the top-right to open
the same form used for new bookings, pre-filled with the current values.
The booking reference is shown but is not editable - it never changes.

## What you can change

Everything in the create form, plus:

- **Status**: all six statuses are available (including Completed and
  Cancelled - which aren't on the create form)
- **Cancellation reason**: a new field at the bottom. Fill it in when
  you set the status to Cancelled; it shows in a callout on the detail
  page so the next person knows why.

## How space changes are reconciled

Spaces are synced - not just re-created. When you submit:

- Spaces you **deselected** are removed from the booking
- Spaces you **added** are attached to the booking
- Spaces that stayed get their start/end realigned to the new window
  (so the overlap check sees the new time range)

If any space change would create an overlap with another definite or
completed booking on that space, the whole save is rolled back and
the form re-renders with an error explaining which booking conflicts.

## Cancelling a booking

There's no dedicated Cancel button right now - set the status to
**Cancelled** in the Edit form and fill in the reason. On save:

- The cancellation timestamp is stamped automatically
- The cancellation reason is highlighted in a red callout at the top of
  the booking detail page
- The booking's spaces are released - other bookings can now use the
  same window

Cancelled bookings still show in the index by default (they're part of
the historical record). Filter by status to hide them.

## Special case: completed bookings

There's nothing stopping you from editing a Completed booking, but in
practice you shouldn't - the audit log tracks every change, and editing
post-event creates a misleading record. If something needs correction,
add it in Notes rather than rewriting history.
