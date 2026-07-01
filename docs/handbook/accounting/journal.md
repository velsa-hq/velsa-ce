---
title: General journal
section: Accounting
order: 53
surfaces:
  - route: /accounting
    method: GET
  - route: /accounting/journal
    method: POST
  - route: /accounting/journal/{journalEntry}/reverse
    method: POST
  - component: accounting/index
tour_ids:
  - je-new
  - je-reverse
---

The **general journal** (`/accounting`, or **Finance -> Accounting**) is
the running ledger of every journal entry - the debits and credits
behind the money the system moves. Most entries post **automatically**
from operational activity (invoices issued, payments captured, refunds),
and the page feeds the periodic **export** to your accounting
system.

:::video journal-entries

## What you see

Each row is one **leg** - a single debit or credit against a chart-of-
accounts code, with its fund, venue, description, and which export batch
(if any) has claimed it. Filter by venue or to **only unexported**
entries; the header shows running debit/credit totals and whether the
ledger is in balance.

## Posting a manual entry

Automated postings can't capture everything - accruals, deferrals,
reclassifications, and corrections need a human. With the
**`accounting.post_journal`** permission, **+ New journal entry** opens a
multi-line form:

- Pick a **posted date**, an optional **venue**, and a **description**
  that ties the legs together.
- Add lines - each is one **account** (leaf/postable accounts only), an
  optional **fund**, and either a **debit** or a **credit**.
- The form tracks debit vs. credit totals live and won't submit until
  the entry **balances** (debits = credits, greater than zero) - the
  invariant the whole ledger depends on.

Posting into a **closed fiscal year** is refused. New entries are
unexported until the next batch claims them, so they flow downstream with
everything else.

## Reversing an entry

Ledgers are **append-only** - entries are never edited or deleted. To
undo a manual entry, use **Reverse**: it posts the mirror of every leg
(debit↔credit) on today's date, linked back to the original. An entry can
only be reversed once, and only manually-posted entries are reversible
here (system-generated entries are corrected through their own flows -
e.g. a refund reverses a payment).

## See also

- [Chart of Accounts](/docs/accounting/chart-of-accounts) - the postable
  account codes
- [Funds](/docs/accounting/funds) - fund scoping
- [General ledger export](/docs/accounting/ledger-export) - how journal entries
  leave the system
