---
title: Ad-hoc report builder
section: Reports
order: 61
---

The ad-hoc report builder lets non-developers compose reports from a
catalog of pre-vetted datasources - no SQL, no code, no DBA tickets.
Saved reports appear on `/reports` alongside the named reports.

:::video report-builder

## Where it lives

`/admin/report-builder` is the index of saved reports. From there you
can:

- **Create** -> wizard to pick a datasource + dimensions + metrics
- **Edit** -> modify an existing saved definition
- **Run** -> execute and see results inline
- **Delete** -> remove the definition (does not affect past runs)

## How a report is built

1. **Pick a datasource** - one of:
   - Bookings
   - Exhibitor orders
   - Invoices
   - Journal entries
   - Work orders
   - Audit events
2. **Pick dimensions** - what columns to group by (e.g. venue, status,
   month)
3. **Pick metrics** - what to aggregate (count, sum of total_cents,
   etc.)
4. **Add filters** - optional WHERE clauses; the form constrains you
   to fields the datasource exposes so injection isn't possible
5. **Save + name** the definition; it now appears on `/reports`

## Why a datasource catalog instead of raw SQL

The datasource catalog is the security boundary. Each datasource
declares exactly which fields can be selected, grouped, and filtered
on. A request that asks for an unknown field is rejected; a request
that tries to bypass the form is rejected. End users never write or
see SQL.

Money filters work in dollars - type `1000` in the UI to filter on
$1,000. The system handles the cents conversion internally.

## Running a saved report

Saved reports live at `/reports/{slug}` once registered (the slug is
derived from the definition's name). The same chart + filter + CSV
plumbing applies - a saved report behaves like a named one from the
user's perspective.

## Run history

Every execution captures the parameters, duration, and row count for
later review - useful for noticing a slow report or auditing who
pulled what. A browse-runs UI is a roadmap item; for now, contact
your administrator if you need to inspect historical runs.

## What you can't do (yet)

- Joins across multiple datasources - each report is single-source
- Window functions (running totals, rank, lag)
- Per-row formulas that compose multiple fields
- Scheduled delivery - saved reports are still pull-only

For anything outside that envelope, the report still needs to be a
named report built in code.
