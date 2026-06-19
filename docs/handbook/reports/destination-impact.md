---
title: Destination impact report
section: Reports
order: 61
surfaces:
  - route: /reports/destination-impact
    method: GET
  - component: reports/show
tour_ids:
  - rpt-destination-impact
  - rpt-impact-pace
---

The **Destination impact** report answers the question a tourist-
development office actually lives by: *how much tourism is this venue
driving?* It rolls every booking up into **room nights** and
**estimated economic impact**, measured against your goal - the
language a convention bureau or county TDD reports to its board, not
just another revenue table.

## What it shows

For the selected period (fiscal year, quarter, or month) and venue:

- **Room nights** - booked total, split **definite vs. tentative** so
  you can see committed business separately from holds.
- **Estimated economic impact** - the dollar value of the events'
  tourism spend (see *How the numbers are built* below).
- **Pace to goal** - booked room nights (and impact) against the
  period's target, so you know at a glance whether you're ahead of or
  behind plan.
- **Calendar utilization** - how much of the available space-time the
  bookings consume over the window.
- **By segment / by month** - the same totals broken out by market
  segment (corporate, association, SMERF, government, consumer...) and
  across the months in the window, so you can see where the business
  is coming from.

Like every report, it filters by date range / venue / status, and
exports to CSV, Excel, and PDF.

## How the numbers are built

The figures are **estimates**, calculated entirely from data you
already capture - there's no hotel feed or outside integration. Two
ingredients combine:

**1. Inputs from each booking** (entered when the event is booked):

- Estimated attendance
- The room block - rooms × nights = the booking's **room nights**
- Event days (from the booking's start/end)
- Market segment and origin

**2. Constants you configure once** in **Admin -> System settings ->
Destination impact** (your TDD sets these from its own tourism
research):

- Average daily spend per delegate
- Average room rate
- An impact multiplier

The report then computes, per event:

```
room-night spend  = room nights × average room rate
delegate spend    = attendance × event days × daily spend per delegate
economic impact   = (room-night spend + delegate spend) × multiplier
```

Because the math is transparent and the constants are yours, the
output is a defensible estimate in the same style the
[Destinations International Event Impact model](https://destinationsinternational.org/event-impact-calculator)
uses - not a black box.

## Estimated vs. actual

Room nights default to the **estimate** from the room block. After an
event, if you receive an actual room-pickup figure (from the hotels or
the planner), enter it on the booking and the report will show
**estimated vs. actual** side by side. Leaving it blank simply keeps
the estimate - no reconciliation is required for the report to work.

## Setting the goal

Pace is measured against a **room-night (and optional economic-impact)
target** for the period. Set it where you set your other sales goals;
with no target set, the report still shows the totals - it just omits
the pace bar.

## Where to go next

- [Creating a booking](/docs/bookings/creating-a-booking) - where room
  block, segment, and attendance are captured
- [Reports overview](/docs/reports) - filters, exports, the full catalog
