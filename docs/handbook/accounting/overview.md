---
title: Accounting overview
section: Accounting
order: 45
---

The Accounting section covers the financial side of the system -
invoicing, payments, refunds, fund accounting, the chart of accounts,
fiscal-year budgets, and the general-ledger export pipeline. Everything that
posts a journal entry passes through one of the services described
below, so the journal is always a complete record of what moved money.

## What lives where

| Surface | Path | Use it to... |
| --- | --- | --- |
| Accounting journal | `/accounting` | Read every posted journal entry, filterable by account / fund / date / source |
| Invoices | `/admin/invoices` | Browse, issue, void, write off, record payments, refund |
| Chart of Accounts | `/admin/chart-of-accounts` | See the canonical account codes the system posts to |
| Funds | `/admin/funds` | See the fund definitions used to scope per-fund reports |
| Fiscal years + budgets | `/admin/fiscal-years` | Open/close fiscal years, set per-account budgets, view variance |
| Export templates | `/admin/export-templates` | Configure the column layout of GL batch exports |

## Core concepts

**Journal entries** are append-only. Every payment, refund, write-off,
deposit, and balance issuance posts a balanced debit/credit pair. The
entries reference their `source_type` and `source_id` so any total can
be drilled back to the originating record.

**Invoices** are polymorphic. A single `Invoice` model handles
exhibitor orders, booking deposits, and booking balances - same
lifecycle, same dunning, same statement generation.

**Funds** scope financial activity for governments + non-profits that
need to track multiple cost centers as separate books. Today every
posting defaults to the General Fund; per-fund scoping per booking is
on the roadmap.

**Chart of Accounts** is the whitelist of valid account codes. Posts
that reference an unknown code fail at write time so the journal can't
drift out of plan.

## Where to go next

- [Invoicing lifecycle](/docs/accounting/invoicing) - issue, send, pay, dun, void, write off
- [Recording payments](/docs/accounting/payments) - manual + online (BluePay)
- [Refunds](/docs/accounting/refunds) - per-payment + invoice-level
- [Dunning](/docs/accounting/dunning) - past-due escalation
- [Chart of Accounts](/docs/accounting/chart-of-accounts)
- [Funds](/docs/accounting/funds)
- [Fiscal-year budgets](/docs/accounting/fiscal-year-budgets)
- [General ledger export](/docs/accounting/ledger-export)
- [Export templates](/docs/accounting/export-templates)
