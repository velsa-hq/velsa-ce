---
title: Financial statements
section: Accounting
order: 54
surfaces:
  - route: /reports/balance-sheet
    method: GET
  - route: /reports/income-statement
    method: GET
  - component: pages/reports/show
tour_ids:
  - report-filters
  - report-apply
  - report-summary
  - report-table
  - report-export-csv
  - report-export-excel
  - report-export-pdf
---

Two standard financial statements are generated straight from the
general ledger and live in [Reports](/docs/reports) alongside every other
named report - so they export to CSV, PDF, and Excel like the rest.

:::video financial-statements

Because they're derived from the same journal entries as the [trial
balance](/docs/accounting/account-ledger-trial-balance), they always tie
out to it - there's no separate set of books to reconcile.

## Balance sheet

**Reports -> Balance sheet.** A point-in-time snapshot as of a date you
pick (default: today):

- **Assets** - what the organization holds (debit-balance accounts).
- **Liabilities** - what it owes (credit-balance accounts).
- **Equity** - fund balance, **plus a "Current period earnings" line**:
  the cumulative net income (revenue - expense) that hasn't been closed
  out yet.

Including current earnings in equity is what makes the statement balance:
**Assets = Liabilities + Equity**. The summary shows a **Balanced: Yes/No**
check - it should always read Yes, because every journal entry balances.

Optionally filter by **venue**.

## Income statement

**Reports -> Income statement.** Revenue and expenses over a period (from /
to dates, defaulting to **year-to-date**), with **net income** =
revenue - expense at the bottom. Optionally filter by **venue**.

The net income an income statement reports for a period is the same number
that flows into the balance sheet's "Current period earnings" line for the
matching as-of date.

## How balances are signed

Each account is shown on its **normal side**: asset and expense balances
are debit-positive; liability, equity, and revenue balances are
credit-positive. Accounts with no activity in the window are omitted.
