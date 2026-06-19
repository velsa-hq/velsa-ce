---
title: Audit log
section: Admin
order: 71
---

Every change to a meaningful record - bookings, contracts, clients,
work orders, users - writes an audit entry. The Admin -> Audit page
(`/admin/audit`) is the searchable, filterable view of that log. It's
append-only by design: entries can't be edited or deleted, even by a
super-admin, even with direct database access. The database itself
refuses delete/update operations against the audit table.

## What gets logged

Every meaningful record class writes an audit entry on create,
update, delete, or restore. Each entry includes:

- **Who** - the authenticated user (or "system" for background jobs)
- **When** - UTC timestamp
- **What** - record type + ID
- **Action** - created / updated / deleted / restored
- **Diff** - the before/after for every changed field
- **Request context** - IP, user agent, route name

Sensitive fields (encrypted tax IDs, payment tokens, etc.) are
excluded from the diff so the audit log never inadvertently leaks
secrets.

## Filtering

The audit page supports:

- **Date range** - start and end dates (inclusive)
- **User** - filter by which user did the action
- **Subject type** - Booking, Contract, Client, User, etc.
- **Action** - created / updated / deleted / restored

Filters compose. The URL reflects all filters so a filtered view of
the audit log is a shareable link.

## CSV export

The page has a download button that streams the filtered audit log as
CSV. The CSV includes the full diff JSON in one column so you can
post-process in Excel or pipe it into another tool. Filenames include
the export date.

## Why append-only

Government procurement and accounting controls - and most enterprise
ones - require an unalterable record of who did what. The database-
level write protection means that even an administrator with direct
database access can't doctor an audit row. The only way to "fix" an
audit entry is to insert a compensating entry that references it;
never modify in place.
