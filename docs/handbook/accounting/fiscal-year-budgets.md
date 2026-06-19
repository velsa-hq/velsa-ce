---
title: Fiscal-year budgets
section: Accounting
order: 52
surfaces:
  - route: /admin/fiscal-years
    method: GET
  - route: /admin/fiscal-years/{label}
    method: GET
  - component: pages/admin/fiscal-years/index
  - component: pages/admin/fiscal-years/show
tour_ids:
  - fiscal-years-create
  - fiscal-years-list
  - fiscal-years-manage
  - budget-set-form
  - budget-line-table
  - budget-year-status
  - budget-variance-report
  - budget-close-year
---

Fiscal years live at `/admin/fiscal-years`. Each fiscal year is a
date window (e.g. October 1 -> September 30) with a status of `open`
or `closed` and a per-account budget table.

:::video fiscal-year-budgets

## What a fiscal year tracks

| Field | Purpose |
| --- | --- |
| Label | e.g. `FY2027` |
| Starts on | First day of the year |
| Ends on | Last day of the year |
| Status | `open` (postings allowed) or `closed` (locked) |
| Budgets | A per-account amount in cents, set by the admin |

The "current" fiscal year is computed from today's date - whichever
open fiscal year contains today is the default for reports.

## Setting budgets

1. `/admin/fiscal-years` -> click into the year
2. **Set / update budget line** form -> pick an account from the CoA
   (and optionally a fund) -> enter the annual budget amount in dollars
3. Repeat per account; you only budget the accounts you care about
   variance reporting on (typically revenue + expense lines)

Budgets are stored per fiscal year, so adjusting next year's budget
doesn't disturb this year's variance numbers.

**Editing + removing.** Each budgeted row has inline **Edit** (change
the amount in place) and **Delete** (with a confirmation) actions.
Re-submitting the form for an account that's already budgeted updates
it rather than adding a duplicate. Both are refused once the year is
**closed** - reopen it first.

## Closing a year

**Close** locks the fiscal year. New journal entries dated within
that window are refused; corrections require **reopen** first, which
posts an audit row recording who reopened and why.

Closing is a soft signal - the closing journal entries (transferring
revenue + expense to fund balance) aren't auto-posted today. That's a
roadmap item.

## Variance reporting

`/reports/budget-vs-actual` is the canonical variance view - reachable
directly or via the **Variance report (PDF / XLS)** button on the
fiscal-year page, which opens it pre-filtered to that year. Like every
named report it exports to **CSV, PDF, and XLSX** from the report page.
For each budgeted account:

- **Budgeted** - what you set at the start of the year
- **Actual** - sum of posted journal entries for that account in the
  fiscal year window
- **Variance** - computed direction-aware:
  - Revenue / Liability / Equity -> favorable when actual > budgeted
  - Asset / Expense -> favorable when actual < budgeted
- **Used %** - `actual / budgeted` formatted as a percentage

The report accepts a fund filter so you can run variance per fund,
and a fiscal-year filter to look at past years.

## What's not wired yet

- Auto-rolling budgets (copy last year's budget + a % adjustment)
- Mid-year budget revisions with an audit trail of changes
- Cash-flow forecast based on remaining budget + open invoices
