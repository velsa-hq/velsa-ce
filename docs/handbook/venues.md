---
title: Venues
section: Venues
order: 5
surfaces:
  - route: /venues
    method: GET
  - route: /venues
    method: POST
  - route: /venues/create
    method: GET
  - route: /venues/archive
    method: GET
  - route: /venues/{venue}
    method: GET
  - route: /venues/{venue}
    method: DELETE
  - route: /venues/{venue}/spaces/create
    method: POST
  - route: /spaces/{space}
    method: GET
  - route: /spaces/{space}
    method: PUT
  - component: venues/index
  - component: venues/show
  - component: venues/{create,edit,archive}
  - component: spaces/{show,create,edit}
tour_ids:
  - venue-new
  - venue-name
  - venue-submit
  - image-upload
  - venue-add-space
  - venue-space-open
  - venue-space-edit
  - venue-new-work-order
  - venue-add-booking
  - space-add-booking
  - space-name
  - space-kind
  - space-bookable-unit
  - space-parent
  - space-submit
  - space-retire
---

A **venue** is one of your organization's bookable facilities. Each
venue owns its own set of **spaces** (rooms, halls, terraces, arenas,
etc.) that bookings actually occupy.

## The venues index

`/venues` lists every venue as a card with status (Active / Coming
soon / Retired), city/state, time zone, and the number of spaces.
Click a card to open the venue's detail page. The header has a
**+ New venue** button and an **Archive** link (retired venues).

## Creating a venue

**+ New venue** opens `/venues/create`. Fields: name, address
(building/suite, street, city, state, ZIP), phone, website, time
zone, a summary, an **Active** toggle, and an optional photo (see
[Photos & images](#photos--images)). The URL
**slug** is
generated from the name automatically (and de-duplicated) - you
don't set it. Save lands on the new venue's detail page, where you
then add its [spaces](#managing-spaces).

## The venue detail page

Click any venue card on the index, or click a venue name anywhere it
appears (booking detail, lead detail, contract detail) to open
`/venues/{slug}`. Venues route by slug - the URL is
`/venues/riverside-convention-center`, not a numeric id.

The detail page shows:

- **Header** - name, status chip, slug + city/state + time zone, with
  **Edit** and **Archive** buttons
- **Stats card** - lifetime bookings, confirmed revenue (definite +
  completed), upcoming count, total spaces, total capacity
- **About** - the venue's marketing summary (free-form text)
- **Location & contact** - full address, phone, and website (shown
  only when any are set)
- **Spaces** - every space configured on the venue, with kind,
  capacity, area, and bookable unit (hourly/daily/multi-day)
- **Upcoming bookings** - next 50 hold/tentative/definite bookings on
  the venue, sorted by start date; each row links to the booking.
  **+ Add booking** opens the booking form with this venue pre-selected.
- **Active work orders** - open (not completed/cancelled) work orders
  for the venue; each row links to its [detail page](/docs/operations/work-orders),
  overdue ones are flagged, and **+ New work order** opens the create
  form with this venue pre-selected. **View all** jumps to the
  work-orders list filtered to the venue.
- **Recurring work-order templates** - preventive maintenance
  templates scheduled on the venue (e.g. weekly HVAC checks, monthly
  fire-extinguisher inspections); each row shows the human-readable
  recurrence and the materialization lookahead

## Editing

Click **Edit** in the top-right of the detail page to open
`/venues/{slug}/edit`. Editable fields:

- Name
- Address - building/suite, street, city, state (2-letter), ZIP
- Phone + website
- Time zone
- Summary (description shown on the index)
- Active toggle - unchecking removes the venue from the dropdowns and
  the calendar without deleting it

Slug is not editable - it's the URL key for the venue and changing it
would break any external links.

## Archiving & restoring

**Archive** (top-right of the detail page) soft-deletes the venue -
it drops off the active index and dropdowns but is never truly
deleted (its bookings, spaces, and history are preserved). Archived
venues live under **Archive** (`/venues/archive`), each with a
**Restore** button to bring it back to active. Prefer archiving over
deletion for any venue that's seen real use.

## Photos & images

Both venues and spaces carry **one display image**, set from the image
picker on their create/edit forms:

- **Upload a photo** - a JPG/PNG/WebP/GIF (up to 5 MB). It's stored via
  the media library and cropped to a thumbnail for cards and the page
  header.
- **Leave it blank** - every venue and space gets an auto-generated
  **identity graphic**: a soft, low-poly colour mesh that's unique and
  stable per record. It's purely a visual fingerprint so you can tell
  venues and spaces apart at a glance, not just by name - no two look
  alike, and a given record always shows the same one.

An uploaded photo always wins over the generated graphic; **Remove
image** clears the photo and reverts to it. Images appear on the
**venues index cards**, the venue **detail header**, and each **space
card**.

## Managing spaces

The **Spaces** card on the venue detail page is where individual
spaces are created and edited.

:::video add-a-space

- **+ Add space** (top-right of the card) opens a form at
  `/venues/{slug}/spaces/create`. Fill in name, **kind**, bookable
  unit, optional capacity / area, and an optional **parent
  space**.
- **Click a space's image or name** to open its detail page at
  `/spaces/{id}` (see [The space detail page](#the-space-detail-page)).
- **Edit** on any space card opens `/spaces/{id}/edit` - the same
  form, pre-filled. (**Floor plan** still opens the constraints
  editor for walls / columns / outlets.)
- **Retire space** (on the edit form) soft-deletes the space so it's
  no longer bookable, keeping its history. A space with sub-spaces
  can't be retired until those are retired or reassigned first.

**Parent space** makes a space a sub-space (e.g. ballroom sections
under a grand ballroom). The system prevents a space from being its
own ancestor and from pointing at a parent in another venue. See
[Partitioned spaces](/docs/bookings/partitioned-spaces) for how the
parent/child tree drives bookable combinations.

**Kind** is chosen from a managed taxonomy. The starter set (room,
ballroom, outdoor field, arena, stall, RV pad, cabin, barn, terrace,
zone) can be extended or curated by admins - see
[Space kinds](/docs/admin/space-kinds). Only **active** kinds appear
in the dropdown.

## The space detail page

Clicking a space's image or name opens its detail page at
`/spaces/{id}` - a read-only view of one space:

:::video space-detail

- **Header** - the space image, name, kind, a link back to its venue,
  and (for a sub-space) a link to its parent space. **Edit** and
  **Floor plan** buttons sit on the right.
- **At a glance** - capacity, area, bookable unit, number of
  sub-spaces, and the upcoming-booking count.
- **Attributes** - any extra key/value metadata set on the space.
- **Sub-spaces** - the space's children (if any), each linking to its
  own detail page.
- **Upcoming bookings** - the next bookings that occupy this space,
  linking to the booking and its client. **+ Add booking** opens the
  booking form with this venue *and* space pre-selected.
- **Blackouts** - active/upcoming blackouts on this space. Blackouts
  are added and removed from the venue page.
