---
title: Event narrative
section: Bookings
order: 15
surfaces:
  - route: /bookings/{booking}/narratives
    method: POST
  - component: bookings/show
tour_ids:
  - booking-narrative-add
---

:::video event-narrative

The **Event narrative** card on the booking detail page is a
chronological diary staff append to over the life of the booking -
call summaries, decisions, site-visit recaps, anything the next
person reading the booking needs to know.

It's different from the booking's **Notes** field (a single piece of
text that gets overwritten on every save) and different from the
**Audit log** (which tracks what fields changed on the record, not
human narrative).

## When to use it

- The client called and asked for an A/V upgrade
- The sales rep met them on site to walk the floor
- A pricing decision was made that future readers should understand
  ("we waived the setup fee because they're a repeat client")
- An email exchange you want preserved alongside the booking
- Any other context the next person opening this booking should see

## Adding an entry

1. Open the booking's detail page
2. Click **+ Add entry** on the Event narrative card
3. Pick a **Kind** - Note / Call / Email / Meeting / Site visit /
   Decision
4. Optionally set **Happened at** - defaults to right now, but you
   can back-date an entry if you're catching up on notes from earlier
   in the week
5. Write the body of the entry - up to 5,000 characters
6. **Append entry**

The entry appears at the top of the list, attributed to you, with the
timestamp you set.

## Append-only by convention

Entries can't be edited or deleted from the UI. If something needs to
be corrected - wrong attribution, wrong date, factual mistake - add a
correction entry rather than rewriting history. The original stays
put. This keeps the narrative honest and means a settlement or
dispute later has an unaltered trail to refer back to.

## Ordering

The list sorts by **Happened at** (newest first). Back-dated entries
slot into the timeline where they belong, not at the top - so if you
add a note today about a call that happened last Tuesday, it appears
in the right chronological position.

## Who sees it

Anyone with read access to the booking sees the full narrative -
ops, sales, accounting, work-order staff. There's no per-entry
visibility setting. Keep that in mind when deciding what belongs in
the narrative vs. a private email.

## Auto-generated entries

The system also appends **System**-kind entries on its own when
certain lifecycle events fire, so the narrative reflects the full
story even when no one types anything in. Today these auto-entries
cover:

| Trigger | What the entry says |
| --- | --- |
| Booking status changes (e.g. Hold -> Tentative -> Definite) | "Status changed from X to Y." |
| Contract sent for signature | "Contract REF sent for signature." |
| Contract fully signed | "Contract REF fully signed." |
| Contract declined | "Contract REF declined." |
| Contract expired or partially signed | Corresponding verb |
| Booking invoice issued | "Invoice NUM issued (deposit, $X)." |
| Booking invoice refunded | "Refund of $X applied to invoice NUM." |

Auto-entries appear interleaved with manual entries in the same
chronological list. They're attributed to the user who performed the
action (if there's an authenticated user) or left unattributed when
they originate from a background job - same posture as the audit log.

Auto-entries don't replace the audit log - they're the human-readable
narrative surface for the same events. The audit log keeps the
machine-readable diff with before/after values; the narrative tells
the story.

## What's coming

- @-mentions of other staff to surface relevant entries on their
  dashboard
- Attachments (PDFs, screenshots) per entry
- Search across all narratives, not just one booking's
- Auto-entries for **payment received** events (today only refunds
  and issuance log; capture-side payments only get an audit entry)
