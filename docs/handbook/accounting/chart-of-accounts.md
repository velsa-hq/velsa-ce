---
title: Chart of Accounts
section: Accounting
order: 50
surfaces:
  - route: /admin/chart-of-accounts
    method: GET
  - route: /admin/chart-of-accounts
    method: POST
  - route: /admin/chart-of-accounts/{chartOfAccount}
    method: PUT
  - component: admin/chart-of-accounts/index
tour_ids:
  - coa-new
  - coa-edit
---

The Chart of Accounts (CoA) is the whitelist of valid account codes
the system can post journal entries against. It lives at
`/admin/chart-of-accounts` and ships seeded with a standard
government-fund chart.

:::video chart-of-accounts

## How it's structured

The seeded chart is organized by the five account types, each with a
non-postable **roll-up** header and the postable **leaf** accounts
beneath it - for example, under **Assets**: `1010 Cash - Operating`,
`1100 Accounts Receivable`, `1500 Inventory`; under **Revenue**:
`4100 Venue Rental`, `4300 Exhibitor`, `4400 Catering & F&B`; under
**Expense**: `5100 Salaries`, `5900 Bad Debt Expense`. Journal entries
only post to **leaf (postable)** accounts - roll-ups exist for
grouping and reports. The page groups accounts by type with search +
type/status filters; the live list is the source of truth.

## What you can configure

Managing accounts requires the **`accounting.post_journal`**
permission. From the admin CoA page:

- **Add a new account** - code (unique), name, type
  (Asset/Liability/Equity/Revenue/Expense), optional subtype, normal
  balance (auto-derived from the type if left blank), optional parent
  roll-up, postable flag, and an optional active-from / active-to
  window.
- **Edit** an account - name, description, parent, postable flag, and
  active window are always editable.
- **Retire / reactivate** - set an **active-to** date and future
  postings refuse the code while existing entries are untouched; clear
  it to reactivate.
- **Reparent** - change an account's roll-up (cycles are refused).
- **Delete** - only for an account with no journal entries and no
  child accounts; otherwise retire it instead.

What you **can't** do:

- Delete an account that has historical journal entries or children
  (the system refuses to break the audit trail)
- Change an account's **code** once entries reference it (the code is
  denormalized onto every entry)
- Change **type** once entries exist - the type drives the
  normal-balance and variance math; changing it mid-stream would
  invalidate every report that's been run

## How postings reference the CoA

Every journal entry references one of the codes here. At write time
the system validates the code exists and is active. Which account the
**automated** posting paths credit/debit (e.g. the revenue account at
invoice issuance) is set in `config/accounting.php`
(`posting.revenue_accounts` and friends) rather than on this page -
adding an account here makes it available for **manual** journal
entries immediately, but wiring it into an automated path is a config
change.

## Per-fund accounting

Funds are a separate axis from accounts. The same CoA applies inside
each fund - Cash in the General Fund is a different ledger row from
Cash in the Capital Projects Fund, even though both post against
`1010`. See [Funds](/docs/accounting/funds).
