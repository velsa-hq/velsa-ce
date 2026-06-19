---
title: Creating a booking
section: Bookings
order: 11
---

From the bookings index, click **+ New booking** to open the create
form. Required fields are marked; everything else can be filled in
later via Edit.

:::video create-booking

You don't have to start from the pipeline - a booking can be created
directly (walk-ups, phone bookings, internal events, migrated history).
A few entry points pre-fill the form for you:

- **+ Add booking** on a [venue page](/docs/venues) pre-selects that
  venue.
- **+ Add booking** on a space detail page pre-selects the venue *and*
  checks that space.
- **Convert to booking** from a pipeline lead pre-fills the venue,
  client, value, and dates, and marks the lead converted on save.

## The form, top to bottom

**Event name** - short, human-readable label. Shows in lists and at the
top of the booking detail page. Example: "Smith-Andersen Wedding" or
"Heartland Cattlemen Spring Convention".

**Venue** - pick from the active venues. Selecting a venue cascades
to the **Spaces** checkbox grid at the bottom - only that venue's
spaces appear.

**Client** - pick an existing client from the dropdown. If the client
doesn't exist yet, click **+ New client** to switch the field to an
inline form (name, type, optional email). The client is created in the
same transaction as the booking, so there's no orphan record if the
booking fails.

**Event kind** - the type of event, chosen from the org's
[configured event kinds](/docs/admin/event-kinds) (wedding, conference,
expo, ... by default). Admins can add or hide kinds.

**Status** - on create, the choices are Inquiry, Hold, Tentative, or
Definite. To set Completed or Cancelled, save first and use Edit.

**When** - single date-range button. Click to open a two-month
calendar; pick the start day, then the end day (or click the same day
twice for a single-day event). The Starts-at / Ends-at time pickers
below the calendar set the exact times. The range button shows the
formatted summary (e.g. "Jun 13, 2026 · 10:00 AM - 4:00 PM").

**Estimated attendance** - used for capacity checks and reports.
Optional.

**Total budget (USD)** - total event fee in dollars (with cents). Stored
internally as integer cents so there's no floating-point drift.

**Notes** - internal-only notes. Not shown to the client.

**Spaces** - at least one is required. Each visible space belongs to the
venue you picked above; selecting spaces in another venue would silently
fail server-side validation.

## What happens on save

In one atomic save:

1. If a new client was supplied, the client (and a primary contact, if
   email was given) is created
2. The booking is created and its reference is generated (`BK-YYYY-XXXXX`)
3. Each selected space is attached to the booking with the booking's
   start/end and a default 60-minute setup + teardown buffer
4. If the booking is going in as **Definite** and any selected space
   conflicts with an existing definite/completed booking, the whole
   save is rolled back and you stay on the form with a spaces error

On success, you're redirected to the new booking's detail page with a
toast confirming the reference.
