---
title: Dashboard
section: Getting started
order: 3
surfaces:
  - /dashboard (pages/dashboard)
tour_ids:
  - dashboard-customize
  - dashboard-tile-handle
  - tile-picker-save
---

The Dashboard at `/dashboard` is the first page you see after signing
in. It's a **tile-based home screen** that you compose yourself -
every user picks which tiles they want to see and in what order, so
sales reps, ops leads, and finance can each have a view that matches
their job without anyone having to build a custom report.

:::video dashboard-overview

## Customizing your tiles

The fastest way to rearrange is **drag-and-drop**: hover a tile and a
grip handle appears in its top-right corner - grab it and drop the tile
wherever you want. The new order saves automatically.

For toggling tiles on and off, click **Customize** in the upper right
to open the tile picker. From there you can:

- **Toggle tiles on or off** with the checkbox on the left
- **Reorder tiles** with the up/down arrows on the right (a keyboard- and
  modal-friendly alternative to dragging)
- **Save** to persist your selection, or **Cancel** to discard changes

Your selection is per-user and persists across logins. New users see
a sensible default set, which an admin can choose under
[System settings -> Defaults](/docs/admin/system-settings) ("Default
dashboard tiles"); you can always come back and tweak your own.

The picker only lists tiles for features you have access to - e.g. the
**Past-due invoices** tile appears only if your role grants accounting
access, **My open leads** only with leads access. (Tiles like **Quick
links** and the **KPI strip** are available to everyone.) So the catalog
naturally matches your job.

## What's available

| Tile | What it shows |
| --- | --- |
| **Needs attention** | Things going stale that want a follow-up: tentative bookings with no narrative activity, contracts sent but not viewed, and leads stuck in the "contract sent" stage. Each row links straight to the record. The day-windows (default 14 / 7 / 14) are admin-tunable under [System settings -> Defaults](/docs/admin/system-settings). On by default. |
| **KPI strip** | Open pipeline, AR outstanding, contracts in flight, overdue work orders, today's outline items - a single header row of headline numbers. |
| **Revenue trend (12 months)** | Monthly booked revenue over the trailing 12 months. Definite + completed + tentative bookings combined. |
| **Pipeline by stage** | Bar chart of open leads grouped by stage, with weighted forecast per stage. |
| **Bookings by status** | Distribution of bookings starting in the next 60 days (and recent 30), grouped by status. |
| **Today on the board** | Run-of-show items scheduled for today across all events, ordered by time. |
| **Recent activity** | Last 10 audit-log entries - who did what across the system. Available in the picker, but off in the default set. |
| **My open leads** | Leads currently assigned to you, ordered by expected close date. The sales-rep view of your personal pipeline. |
| **My upcoming bookings** | Bookings you own (or are assigned to) starting in the next 14 days. The sales / ops view of your near-term events. |
| **Past-due invoices** | Invoices past their due date with an outstanding balance, oldest first. The finance / AR view of who's late. |
| **Quick links** | A grid of clickable chiclets you can curate yourself. Click the **+** chiclet to open the picker and check off the sections you want one click away - Contracts, Accounting, Funds, whatever you reach for most. On by default at the top of the dashboard; starts empty until you pick. |

## Tips

- The **KPI strip** spans the full width of the dashboard. Personal-list
  tiles (open leads, upcoming bookings, past-due invoices) are half-width
  and pair nicely side by side.
- Removing a tile doesn't delete anything - turn it back on later and
  it picks up where it left off.
- Tile data refreshes every time you visit the dashboard. There's no
  caching layer, so the numbers are always current.

The catalog will grow over time as new modules contribute their own
tiles. If there's a number you wish you could see at a glance, let us
know - most tiles are quick to add.
