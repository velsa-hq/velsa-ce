---
title: Venue exhibitor handbook
section: Exhibitors
order: 30
surfaces:
  - route: /venues/{venue}/edit
    method: GET
  - route: /venues/{venue}
    method: PUT
  - component: venues/edit
  - route: /portal/handbook
    method: GET
  - route: /portal/handbook/acknowledge
    method: POST
  - component: portal/handbook
tour_ids:
  - venue-exhibitor-handbook
  - venue-handbook-publish
  - portal-handbook-acknowledge
---

Each venue can publish an **exhibitor handbook** - the venue's rules and policies
(prohibited items, load-in/out, smoking, storage, damage charges, and so on) -
and capture each exhibitor's **acknowledgement** that they've read it.

:::video venue-handbook

## Writing the handbook (admin)

On a venue's **Edit** page, the **Exhibitor handbook** field
(`data-tour-id="venue-exhibitor-handbook"`) takes Markdown - headings, lists,
emphasis, and links all render. Tick **Publish exhibitor handbook**
(`data-tour-id="venue-handbook-publish"`) and save to make it visible to that
venue's exhibitors; untick to pull it back to draft. A handbook only publishes
when it has content.

## Acknowledging it (exhibitor portal)

When a venue has a published handbook, its exhibitors see a **Handbook** page in
the portal showing the rendered rules. The exhibitor reads it and clicks
**I acknowledge** (`data-tour-id="portal-handbook-acknowledge"`), which records a
dated acknowledgement against that exhibitor and venue. The page then shows the
acknowledgement date; acknowledging again is idempotent (it keeps the first
date).

> Acknowledgement is per exhibitor, per venue - the venue is resolved from the
> exhibitor's event. It's a record that the policies were presented and accepted,
> not a gate on ordering.
