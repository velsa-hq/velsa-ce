---
title: Pipeline
section: Pipeline
order: 20
surfaces:
  - /pipeline (pages/pipeline/index)
  - /pipeline/archive (pages/pipeline/archive)
  - /leads/create (pages/leads/create)
  - /leads/{id} (pages/leads/show)
tour_ids:
  - pipeline-new-opportunity
  - pipeline-overdue-badge
  - lead-create-form
  - lead-clone
  - lead-reopen
  - lead-archive
  - archive-search
---

The Pipeline tracks **opportunities** - prospect events that haven't
been committed to a booking yet. Each opportunity (internally, a
"lead") belongs to a client and is owned by a sales user; the page
(`/pipeline`) groups them by stage in a left-to-right kanban view.

:::video pipeline-overview

## Stages

Opportunities move through six stages. Each stage has a default
probability that feeds the pipeline value forecast.

| Stage | Default probability | Meaning |
| --- | --- | --- |
| New | 10% | Just-received inquiry, not yet vetted |
| Qualified | 25% | Vetted, real budget + date in mind |
| Proposal sent | 50% | We've sent pricing / a proposal |
| Contract sent | 80% | Contract drafted and sent for signature |
| Won | 100% | Closed; a booking should exist or be created |
| Lost | 0% | Client went elsewhere or postponed |

Lost opportunities are kept for reporting but are visually
de-emphasized.

Stage **names and default probabilities** are configurable - an admin
can rename the funnel and retune the forecast weights under
[Pipeline stages](/docs/admin/pipeline-stages).

## Card order and the overdue cue

Within each column, cards are sorted by **expected close date, soonest
first** - the most time-sensitive opportunities float to the top.
Undated opportunities sink to the bottom, with larger deals breaking
any ties.

When an open opportunity's expected close date slips into the past, its
card gets an **amber border and an "Overdue" badge** next to the close
date - a standing nudge that the date needs either action or a reset.
Overdue is purely a visual cue; it never moves a card on its own. An
admin can set a grace period before the flag kicks in (System settings ->
Defaults -> "Pipeline overdue grace").

## Creating an opportunity

Use **+ New opportunity** (top-right of the board) to open the create
form at `/leads/create`. Pick the client, optionally a venue, a name,
and a starting stage (only the open stages - New through Contract sent
- are selectable; you reach Won/Lost by working the opportunity, never
by creating one there directly). Estimated value, expected close date,
and source are optional. Probability is set automatically from the
stage and can be tuned later from the edit form.

## Opportunity fields

- **Name** - what to call this opportunity (e.g. "Riverside Bridal
  Affair - spring showcase"). Doesn't have to match the eventual
  booking name.
- **Client + venue** - which client and which of your venues
- **Estimated value** - your best guess at total revenue, in dollars
- **Expected close date** - when you expect a yes/no (also the card
  sort key and the overdue trigger)
- **Source** - referral, website, event, cold outreach, partner
- **Lost reason** (Lost stage only) - budget, timing, competition, fit

## Activities

Each opportunity can have one or more **activities** attached - these
are follow-ups, calls, site visits, etc. Activities have:

- **Kind** - call, email, meeting, task, site visit, note
- **Summary** - short description ("Discovery call", "Tour Grand Ballroom")
- **Due date / time** - when you committed to do it
- **Completed at** - stamped when you mark it done

## Opportunity detail page

Click any card on the pipeline to open its detail page
(`/leads/{id}`). The detail page shows:

- All the fields (estimated value, weighted value, expected close,
  source, owner)
- A timeline of activities, split into Open and Done sections
- A small "Add an activity" form to log a new call/email/meeting/note
  without leaving the page
- **Edit** -> the `/leads/{id}/edit` form for changing the stage,
  probability, value, source, and reason-lost
- **Clone** -> spins up a fresh New opportunity from this one (same
  client, venue, value, source, and notes) and drops you on its edit
  form. Handy for recurring clients or re-pursuing a Lost deal without
  disturbing its history.
- **Reopen** (closed opportunities only) -> reinserts it into the funnel
  at Qualified, clears the Lost reason, and un-archives it. An
  opportunity that already converted to a booking can't be reopened -
  it's a real booking now; clone it instead.
- **Archive** (closed opportunities only) -> moves it off the board into
  the archive immediately, ahead of the automatic window.
- For Won opportunities, a **+ Convert to booking** button (or a ✓ link
  to the already-converted booking - see below)

Marking an activity done is a single click on its checkbox; the row
toggles between Open and Done in place without a full page navigation.

## Moving opportunities through the funnel

The kanban board is **drag-and-drop**. Grab a card by clicking and
holding for ~5px of mouse travel (this small threshold prevents
accidental drags when you actually meant to open the card), then drop
it onto any column. The card moves immediately; the backend updates
the stage and adjusts the probability to the stage default in the same
transaction.

- **Mouse, touch, and keyboard** are all supported. With the keyboard,
  Tab onto a card, press Space to pick it up, arrow keys to navigate,
  Space again to drop, Escape to cancel.
- **Dropping onto Lost** opens a small dialog asking for a reason
  before the move commits. Cancel and the card snaps back to where
  it was. Hit Enter once you've typed the reason to confirm.
- **Dropping onto Won** moves the card immediately. The existing
  **+ Convert to booking** button on the card handles the actual
  booking creation - drag-drop just changes the stage.
- **Reverting a Lost back to open** clears the lost reason so future
  reports don't misrepresent the current state.

If you'd rather change the stage from the edit form (e.g. to tune
probability at the same time), the `/leads/{id}/edit` flow still works
exactly as before.

## Archive (the graveyard)

Closed opportunities don't pile up on the board forever. The
**Archive** link (top-right of the board) opens `/pipeline/archive`, a
searchable list of every opportunity that has aged - or been manually
moved - off the board. Search by opportunity or client name; each row
can be **reopened** or **cloned** right from the list.

Opportunities reach the archive two ways:

- **Automatically.** A nightly job moves closed (Won/Lost)
  opportunities off the board once they've been closed longer than the
  **archive window** - the `Pipeline archive window (days)` setting
  under Admin -> System settings -> Defaults (60 days by default).
  Opportunities whose **event date is still in the future** stay on the
  board no matter how long ago they were closed - the deal is done, but
  the event still matters.
- **Manually.** The **Archive** button on a closed opportunity's detail
  page moves it off immediately.

## Converting an opportunity to a booking

When an opportunity reaches **Won**, a **+ Convert to booking** button
appears on the card. Clicking it opens the booking create form with the
name, client, venue, estimated value (as the total), and expected close
date (as a starting date) already filled in - you just adjust the dates
and pick spaces, then save.

On save:

- A new booking is created with `lead_id` pointing back at the
  opportunity
- The opportunity's `converted_at` is stamped with the current time
- The opportunity's `converted_booking_id` points at the new booking

The card replaces the Convert button with a green `✓ BK-YYYY-XXXXX`
chip that links straight to the resulting booking, so a converted
opportunity is never re-converted by accident - the action won't
reappear even if you reload.
