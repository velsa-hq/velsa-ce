---
title: Exhibitor activity permits
section: Exhibitors
order: 40
surfaces:
  - route: /portal/permits
    method: GET
  - route: /portal/permits
    method: POST
  - route: /portal/permits/{permit}/cancel
    method: POST
  - component: portal/permits
  - route: /admin/exhibitor-permits
    method: GET
  - route: /admin/exhibitor-permits/{exhibitorPermit}
    method: PUT
  - component: admin/exhibitor-permits/index
tour_ids:
  - permit-type
  - permit-details
  - permit-submit
  - permit-cancel
  - permit-status-filter
  - permit-review
---

Some booth activities need the venue's sign-off before show day - food or
beverage **sampling**, **alcohol service**, **open flame** or on-booth cooking,
driving a **vehicle** in for a display, **amplified sound**, or an **oversized /
hanging display**. Exhibitors request these from their portal; venue staff
review each one and approve or deny it, recording any conditions.

:::video exhibitor-permits

## Requesting a permit (exhibitor portal)

The portal's **Permits** page lists everything the exhibitor has requested for
the event and its current state. To raise a new one, pick the activity from the
**Permit type** menu (`data-tour-id="permit-type"`), describe what's planned in
**Details** (`data-tour-id="permit-details"`) - quantities, times, equipment,
anything the venue needs to weigh - optionally attach a supporting document
(a fire-safety certificate, a product list), and click **Submit request**
(`data-tour-id="permit-submit"`). The request lands as **Pending review**.

While a request is still pending the exhibitor can withdraw it with **Cancel**
(`data-tour-id="permit-cancel"`); once staff have decided, it's read-only and a
new request is needed to change anything.

## Reviewing requests (admin)

Staff with the compliance permission see **Exhibitor permits** in the admin
sidebar - the queue of every request across exhibitors. The status filter
(`data-tour-id="permit-status-filter"`) narrows it to **Pending**, **Approved**,
**Denied**, or **Cancelled**; the page opens on the pending work.

Each card (`data-tour-id="permit-review"`) shows the exhibitor, the activity,
their description, and any attached document. **Approve** clears the activity;
**Deny** turns it down. Either way you can leave a note - approval conditions
("propane bottles capped overnight", "sampling cups ≤ 2 oz") or the reason for a
denial - and the decision stamps who reviewed it and when. The exhibitor sees
the outcome and your note back in their portal.

> A permit records that an activity was reviewed and decided - it isn't a gate
> on ordering booth equipment or paying. Approvals and denials are per
> exhibitor, per request.
