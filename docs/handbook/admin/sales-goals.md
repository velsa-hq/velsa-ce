---
title: Sales goals
section: Admin
order: 46
surfaces:
  - route: /admin/sales-goals
    method: GET
  - component: pages/admin/sales-goals/index
tour_ids:
  - sg-salesperson-select
  - sg-year-input
  - sg-period-select
  - sg-goal-amount
  - sg-save-goal
  - sg-goal-remove
---

Set **revenue targets per salesperson** and track them against real booked
revenue. Goals live under **Admin -> Sales goals** and need the *manage sales
goals* permission (county/venue admins and sales managers by default).

:::video sales-goals-admin

## Setting a goal

Pick a **salesperson**, a **year**, and a **period** - *Whole year* for an
annual target or a specific month - then enter the dollar **goal** and save.
Re-saving the same salesperson + period updates the existing goal rather than
creating a duplicate. Remove a goal with the **Remove** action in the table.

## Seeing attainment

Attainment is reported in the **Sales goal attainment** report
(Reports -> Sales goal attainment). For each goal it shows the target, the
**actual booked revenue** attributed to that salesperson for the period
(bookings they own that are *definite* or *completed*), the variance, and the
**attainment percentage**, plus period totals. Filter by year.
