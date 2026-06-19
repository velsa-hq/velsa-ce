---
title: Export templates
section: Accounting
order: 56
---

Export templates control the column layout of the CSV that
[`/accounting/export`](/docs/accounting/ledger-export) generates.
They live at `/admin/export-templates` and let an admin configure the
output without code changes - useful because every general-ledger
import format is slightly different.

## What a template controls

For each column in the output CSV:

- **Source field** - which attribute of the journal entry to render
  (account code, fund code, date, amount, description, source_type,
  source_id, etc.)
- **Output header** - the column name in the CSV
- **Format** - date format, decimal places, sign handling (debits as
  positive vs. signed)
- **Order** - drag-reorder in the admin UI

## Common actions

- **Create** - `/admin/export-templates/create`. Name the template,
  pick columns, drag to order. The form has a live preview against a
  sample batch so you can validate the output before saving.
- **Edit** - once a template has historical batches attached, the
  edit page warns you that downstream consumers may expect
  the current column layout. Editing doesn't retroactively change
  past batches.
- **Set default** - toggle the **Mark as default** flag so new
  batches automatically use this template. Only one template can be
  the default at a time.
- **Delete** - refused if the template has any historical batches
  attached. Deactivate the template instead so it stops being used by
  new exports while the audit trail stays intact.

## Seeded templates

A default `General Ledger CSV` template ships out of the box with the
columns a standard GL importer expects. Most deployments can use it as-is
or copy it as a starting point for a customized variant.

## Preview

The **Preview** action runs the renderer against the most recent
generated batch (or a synthetic sample if no batches exist yet) and
shows the first 20 rows of CSV output. Useful for sanity-checking a
column layout before you mark the template as default.
