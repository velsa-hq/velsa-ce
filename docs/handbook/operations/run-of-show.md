---
title: Run-of-show outlines
section: Operations
order: 42
surfaces:
  - route: /bookings/{booking}/outline
    method: GET
  - route: /bookings/{booking}/outline/items
    method: POST
  - route: /outline-items/{item}
    method: PATCH
  - route: /outline-items/{item}
    method: DELETE
  - route: /outline-items/{item}/tasks
    method: POST
  - route: /outline-item-tasks/{task}/toggle
    method: PATCH
  - route: /outline-item-tasks/{task}
    method: DELETE
  - route: /bookings/{booking}/outline.pdf
    method: GET
  - route: /bookings/{booking}/outline/publish
    method: POST
  - component: bookings/outline
tour_ids:
  - outline-pdf
  - outline-add-item
  - outline-edit-inline
  - outline-template-select
  - outline-description
  - outline-responsible
  - outline-task-add
  - outline-save
  - outline-publish
---

:::video run-of-show

Every booking can have a **run-of-show outline** - the timed
schedule of what happens, when, which department owns it, and
which staff member is responsible. Outlines feed both the printable
day-of **run sheet** and the
[Ops board](/docs/operations/ops-board).

Open an outline from the booking detail page -> **Run-of-show**
button, or directly at `/bookings/{id}/outline`.

## Outline items

Each item has:

- **Title** - what's happening ("Crew setup", "Main course
  service")
- **Department** - the ops team that owns the item, chosen from the
  org's [configured departments](/docs/admin/departments) (Setup, A/V,
  Catering, ... by default)
- **When** - exact start time, and **Duration** in minutes (which
  drives the "ends at" you'll see on the ops board)
- **Description** - optional free text that supports **Markdown**
  (bold, lists, links). It renders formatted on the row and on the
  run sheet
- **Checklist** - optional tickable sub-steps for the item (see
  [Checklists](#checklists) below)
- **Space** - optional; ties the item to a specific space in the
  venue
- **Responsible user** - optional; who on the staff roster owns
  this item

The seeder builds a starting outline for every booking - a base set
covering every department (pre-event huddle, crew setup, A/V check,
catering load-in, doors open, main service, perimeter sweep,
teardown, final clean) plus a few kind-specific items tuned to the
booking's `kind` (sports gets a scoreboard check; weddings get the
ceremony rehearsal; trade shows get the booth load-in window). Real
production outlines start from this base and get tweaked from there.

## Adding and editing items

Everything is edited through one **item dialog**, opened two ways:

- **Add item** opens a blank dialog to create a new entry.
- Clicking an item's **title** (or its **Edit** button) opens the
  same dialog populated with that item.

The dialog header links to the parent booking and shows the venue
(and space, when set), so you always know which event you're
editing. Inside it you set the title, when, duration, department,
responsible user, a **multi-line Markdown description**, and the
item's checklist. **Save** commits; closing the dialog discards
unsaved field changes.

Editing happens in the dialog rather than in the row, so the row -
including its checklist - stays visible behind it. The booking's
times display exactly as entered (venue wall-clock); they are not
shifted into the viewer's timezone.

### Start from a template

When adding a *new* item, the dialog offers a **Start from a
template** picker. Choosing a [run-of-show
template](/docs/admin/run-of-show-templates) **prefills** the
title, department, duration, description, and checklist - nothing
is saved until you hit **Add item**, so you can tweak anything
first. This is the fast path for repeatable activities like an A/V
sound check or a catering load-in.

## Checklists

Each item can carry a **checklist** - the concrete sub-steps for
that activity (an A/V check's "mics on", "confirm levels with FOH",
etc.). Checklist items are stored as real records, not free text:

- Tick them off as you go - straight from the row for quick day-of
  progress, or inside the item dialog.
- The row and the ops board show a **done / total** count (e.g.
  `☑ 2/4`), so a glance tells you how far along an activity is.
- Add or remove steps in the item dialog; templates seed a
  checklist when you create an item from one.

## Responsible-user picker

Each outline item can name one **responsible user** - the person
on-site who owns that step. The picker's candidate pool is exactly
the booking's **staff roster** (see [Staff
roster](/docs/bookings/staff)). You can't assign an item to
someone who isn't actually working the event.

Why the constraint:

- Removes the most common day-of confusion ("who's actually here
  to do this?")
- Forces the roster to be filled in before the outline can name
  owners - which is the right order: who's working -> what they're
  doing

If the dropdown is empty, the roster is empty. Add the people
first, then come back and assign owners.

## The printable run sheet

**Run sheet (PDF)** downloads the outline as a print-ready
document - the day-of artifact ops staff carry. It's generated
server-side (same engine as the booking settlement), grouped by
day and time-ordered, and shows each item's department, time +
duration, Markdown description, checklist (as `☑`/`☐` boxes), and
responsible/space. The header carries the event, venue, date range,
and the outline's published/draft status. Times print as the venue
wall-clock.

## Publishing

Outlines have a `published_version` - **Publish outline** increments
it when you've reviewed the outline and it's ready for the ops
board. Unpublished outlines won't show on the board (this prevents
half-built drafts from appearing in the ops view). The booking
detail page shows the outline's published version + last publish
time.

## Common gotcha

If you change a booking's start time, the outline items DON'T move
automatically - they're absolute timestamps, not relative offsets.
If the booking moves, you'll need to shift each outline item by the
same amount. (Future enhancement: a "shift outline by N hours"
action.)

## See also

- [Run-of-show templates](/docs/admin/run-of-show-templates) - the
  reusable items the "Start from a template" picker draws from
- [Staff roster](/docs/bookings/staff) - the source of the
  responsible-user candidate list
- [Ops board](/docs/operations/ops-board) - where the published
  outline surfaces day-of
