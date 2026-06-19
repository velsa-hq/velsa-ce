---
title: Funds
section: Accounting
order: 51
surfaces:
  - route: /admin/funds
    method: GET
  - route: /admin/funds
    method: POST
  - route: /admin/funds/{fund}
    method: PUT
  - component: admin/funds/index
tour_ids:
  - fund-new
  - fund-edit
---

A **fund** is a separately-tracked set of financial activity that
shares the same chart of accounts but is reported as a distinct book.
Government entities use funds to keep restricted dollars (grants,
capital projects, enterprise activities) from co-mingling with the
general operating dollars.

Government accounting typically requires this - *"a unique set of financial data but share a common set of
locations and calendars."*

## Where it lives

:::video chart-of-accounts

`/admin/funds` lists every fund the system knows about, with search
and type/status filters. The default seed creates:

| Code | Name | Type |
| --- | --- | --- |
| GENERAL | General Fund | General |
| TOURISM | Tourism Development Fund | Special revenue |
| ENTERPRISE | Enterprise Operations Fund | Enterprise |

## What you can configure

Managing funds requires the **`accounting.post_journal`** permission.
From the admin funds page:

- **Add a fund** - code (unique), name, type, optional description,
  optional parent fund, and an optional active-from / active-to window.
- **Edit** - name, type, description, parent, and active window.
- **Retire / reactivate** - set an **active-to** date to retire a fund;
  clear it to reactivate.
- **Delete** - only for a fund with no journal entries and no child
  funds; otherwise retire it instead.

A fund's **code** locks once journal entries are tagged to it (the code
is denormalized onto every entry), and a parent that would create a
cycle is refused.

## What's not wired yet

Journal entries store an optional fund tag. **Per-fund coding** -
automatically tagging a booking's revenue and costs to the right fund
based on venue, account, or booking kind - is still a roadmap item; a
single default fund can be pinned via `config/accounting.php`
(`posting.default_fund`). Until per-fund coding lands, finance can
manually tag entries via the admin journal if cross-fund reporting
matters.

## Reports that respect fund

Today:

- **Budget vs Actual** (`/reports/budget-vs-actual`) - accepts a fund
  filter so each fund can be reported separately
- **Accounting journal** (`/accounting`) - filter by fund

Roadmap:

- Per-fund balance sheet
- Per-fund revenue and expense statement
- Inter-fund transfer journal entries
