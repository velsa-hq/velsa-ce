---
title: Account ledger & trial balance
section: Accounting
order: 54
surfaces:
  - route: /accounting/accounts/{chartOfAccount}
    method: GET
  - route: /accounting/trial-balance
    method: GET
  - component: accounting/account-ledger
  - component: accounting/trial-balance
tour_ids:
  - account-ledger
  - trial-balance
---

The [general journal](/docs/accounting/journal) lists every leg in one
flat stream. Two reporting views slice the same data the way an
accountant actually reads it: the **account ledger** follows one account
over time, and the **trial balance** proves the whole book balances on a
given date. Both are read-only - they post nothing, they only summarize
what the journal already holds.

:::video account-ledger

## Account ledger

Click any **account code** - on the journal, in the
[Chart of Accounts](/docs/accounting/chart-of-accounts), or on a trial
balance row - to open that account's ledger
(`/accounting/accounts/{code}`). It lists every entry against the
account in date order with a **running balance** carried down the page.

- **From / To** narrows the date window. When a **From** date is set, the
  view shows an **opening balance** - the account's net position from
  every entry *before* the window - so the running balance stays correct
  even though earlier rows are hidden.
- **Venue** filters to a single venue's activity in the account.
- The **closing balance** (header and footer) is the opening balance plus
  everything in the window.

Balances read in their natural direction: a net **debit** position shows
`Dr`, a net **credit** position shows `Cr`. That holds for every account
type - a revenue account, which lives on the credit side, simply reads
`Cr` as it accumulates.

## Trial balance

**Trial balance** (button in the journal header, or
`/accounting/trial-balance`) lists every account with activity through an
**as-of** date, each account's net placed in its **debit** or **credit**
column. Optionally scope to a single **venue**.

The point of the report is the bottom line: total debits must equal total
credits. A balanced book is the headline; if the two columns ever
disagree the report flags it, which is your signal that something posted
outside the normal double-entry flow. Each account code links through to
its [ledger](#account-ledger) so you can drill from the summary straight
into the detail behind any figure.

## See also

- [General journal](/docs/accounting/journal) - the flat entry stream and
  manual posting
- [Chart of Accounts](/docs/accounting/chart-of-accounts) - the account
  codes these views roll up
- [General ledger export](/docs/accounting/ledger-export) - how the same
  entries leave the system
