---
title: Find a space
section: Sales
order: 22
---

The **Find a space** tool (under **Sales -> Find a space** in the left
nav, or `/spaces/find`) answers a common sales question in one query:
"Given this date, this attendance, and these preferences - what's the
best-fit space available?" Instead of clicking through venues and
spaces one at a time, you describe the event and the system returns a
ranked list of options.

## What you give it

| Field | Required | What it does |
| --- | --- | --- |
| Starts at | Yes | The start of the event window |
| Ends at | Yes | The end of the event window - must be after Starts at |
| Attendance | No | Filters out spaces whose capacity is below the headcount; also drives the fit score |
| Min sqft | No | Filters out spaces below the requested square footage |
| Kind | No | Filters to a specific space type (ballroom, outdoor field, arena, etc.). The list is admin-managed - see [Space kinds](/docs/admin/space-kinds) |
| Venue | No | Restricts the search to a single venue |

Only the date window is mandatory. Everything else narrows or biases
the search.

## How it ranks

The result list is sorted by **fit score**, where 100 is a perfect
capacity match and the number drops as the space gets larger than
you need:

| Capacity vs. attendance | Score |
| --- | --- |
| Exact match (cap = attendance) | 100 |
| 50% over (cap = 1.5× attendance) | ~67 |
| 2× over | 50 |
| 3× over | ~33 |

Small bonuses:

- **+5** if the space's kind matches your kind filter exactly
- **+3** if the space is in your preferred venue

Result: tighter fits float to the top. A 250-cap ballroom for an
event of 200 ranks above a 1,000-cap hall - better utilization for
both the buyer and the venue.

The **rationale** line under each result spells out the fit in
plain English: "tight capacity fit," "40% over capacity," or "2.5x
larger than needed."

## What "available" means

The same availability rules the booking form enforces:

- **Definite or completed bookings** on the space (or any partition
  parent in its tree) make the space unavailable for the requested
  window. Holds and tentatives don't block - those are soft slots
  that get bumped when a definite locks the time.
- **Blackouts** on the space, on any partition parent, or on the
  parent venue make the space unavailable. The rationale doesn't
  surface this - the space simply doesn't show up.
- **Retired spaces** are excluded entirely.
- **Setup/teardown buffers** - at venues that enforce them, a booking's
  effective (buffered) window is used here too, so a space whose
  turnaround bleeds into your requested time is filtered out rather than
  only being rejected when you try to save.

If no spaces survive the filters, the page suggests widening the
window, relaxing the kind filter, or lowering the attendance
estimate.

## Pairing with the rest of the sales flow

Today this is a **read-only discovery tool** - the page shows you
matches but doesn't auto-fill the booking form. The intended flow is
sales rep finds a fit here -> clicks the venue link to see the space's
context -> opens a new booking and picks the same space manually.

Tighter integration ("Use this space" -> pre-filled booking form) is
a clean follow-up once we know which flow staff actually settle into.

## Common queries

A few realistic shapes the tool handles well:

- **"100-person wedding reception, Saturday afternoon in May"** -
  starts/ends, attendance=100, kind=ballroom (or leave blank).
  Returns tight-fit ballrooms ranked by capacity.
- **"Trade show, 5,000 sqft minimum, mid-September week, any venue"**
  - starts/ends span the week, min_sqft=5000, leave attendance and
  venue blank. Returns every space large enough that's open.
- **"Smith Co's annual gala - preferred venue, 300 guests"** -
  starts/ends, attendance=300, venue=Smith Co's preferred site.
  Returns only that venue's matches.
