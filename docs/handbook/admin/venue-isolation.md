---
title: Venue data isolation
section: Admin
order: 74
surfaces:
  - route: /admin/system-settings
    method: GET
  - component: pages/admin/system-settings/index
tour_ids:
  - system-settings-operations-card
  - venue-isolation-toggle
  - venue-isolation-description
  - save-settings-button
---

By default, every staff member with the right *permission* can see data across
**all** venues - appropriate when one organization runs its venues as a single
operation (the common case). **Venue data isolation** is an optional, stricter
mode for organizations that want each user confined to the venues they work at.

:::video system-settings-venue-isolation

## What it does

Turn on **Venue data isolation** under **Admin -> System settings -> Operations**.
When on, a user only sees records belonging to the venues they hold a role at -
bookings, spaces, work orders, leads, inventory, rate cards, and venue-specific
templates from other venues are hidden everywhere (lists, detail pages, and
global search alike).

## Who still sees everything

Org-wide roles bypass isolation: anyone holding the **"view all venues"**
permission (`venues.view_all`) - by default **super admins** and **county
admins** - continues to see every venue's data, so leadership and central
administration aren't boxed in. Assign or remove that permission per role to
tune who is cross-venue.

## Notes

- **Off by default.** Leaving it off preserves today's behavior exactly; turn it
  on only if you want per-venue confinement.
- Isolation applies to **authenticated user activity**. Background jobs and
  scheduled tasks run unscoped (they operate across the whole system by design).
- Org-level records that aren't tied to a single venue (clients, the chart of
  accounts, etc.) are not venue-partitioned.
