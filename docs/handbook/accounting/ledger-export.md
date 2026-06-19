---
title: General ledger export
section: Accounting
order: 55
surfaces:
  - route: /accounting/export
    method: POST
  - route: /accounting/batches/{batch}
    method: GET
  - route: /accounting/batches/{batch}/void
    method: POST
  - route: /accounting/batches/{batch}/download.csv
    method: GET
  - component: accounting/batch-detail
tour_ids:
  - gl-export
  - gl-batch
  - gl-void
  - gl-acknowledge
  - gl-resend
---

Velsa pushes the period's journal activity to a general-ledger system
as a batched export rather than posting each entry over an API -
easier to reconcile, smaller integration surface, audit-friendly.

The export is GL-agnostic: the rendered layout is fully configurable
via export templates (CSV or fixed-width, column order, labels, format
masks), so the output can match whatever importer your GL system
expects: a major ERP, an accounts-receivable summary, or anything else.

:::video ledger-export

## How it works

**Export to GL** on the accounting journal (`/accounting`) opens a
dialog where you pick the **period** (YYYY-MM) and the **export
template** to render with, then packages that month's pending journal
entries - those not yet attached to a batch - into a new export batch.
The button is gated on the **`accounting.export_ledger`** permission.
The batch:

- Claims which entries are included (stamping `export_batch_id`) so
  they won't be exported twice - those entries now show the batch's
  period in the journal's **Batch** column
- Renders to the chosen **Export template** layout (see
  [Export templates](/docs/accounting/export-templates))
- Stores the rendered file as a downloadable artifact
- Is handed to the configured **delivery transport** (below)
- Writes an audit entry

Once a journal entry is part of a batch, it can't be silently re-
exported in a second batch. If you need to re-export, void the batch
first; the entries detach and re-appear in the pending queue.

## Delivery transports & lifecycle

How a rendered batch travels onward is set by
`config/accounting.php` (`accounting.export.transport`):

- **none** - no automated send. Staff download the file and deliver it
  out-of-band. The batch stays **ready**.
- **email** - the rendered file is emailed to the recipient in
  `accounting.export.email.recipient`. On success the batch moves to
  **sent**; if the send fails it moves to **failed** with the reason.

Further transports (SFTP, a GL API) slot in behind the same interface
without changing the export workflow.

A batch then moves through:

- **ready** -> rendered, awaiting delivery or hand-off
- **sent** -> delivered by the transport
- **acknowledged** -> you've confirmed the GL system accepted it
  (**Mark acknowledged** on the detail page)
- **failed** -> delivery errored; fix the cause and **Resend**

(`empty` and `unbalanced` batches are never auto-delivered - they're
left for review.)

## The batch detail page

The journal page shows a **Recent GL export batches** strip - one
card per batch with its period, status, entry count, and totals.
Clicking a card (or an entry's period in the Batch column) opens the
**batch detail page** (`/accounting/batches/{batch}`), which lists:

- The batch's status, balanced check, template, who created it, the
  sent / acknowledged timestamps, and how it was delivered
- Every journal entry the batch claimed
- A **Download CSV** button - the file matches the export template that
  was active when the batch ran
- **Resend** (re-attempt the transport after a failure) and **Mark
  acknowledged** (close the lifecycle once the GL confirms receipt),
  both gated on `accounting.export_ledger`

## Voiding a batch

If a batch was generated in error, open its detail page and use
**Void batch** (also gated on `accounting.export_ledger`):

1. Confirm with a reason
2. The journal entries detach and re-appear in the pending queue for
   the next export
3. The batch is stamped **voided** with the reason, the time, and who
   did it

Voiding does **not** delete the batch - it's preserved with a void
timestamp so the audit trail is intact, and it doesn't touch the
postings themselves (to undo a posting, reverse the entry).

## Preview / no-transport mode

With the transport set to **none** (the default), the export produces a
downloadable file but doesn't send anywhere - useful for running the
workflow end-to-end during onboarding or acceptance testing. Switch the
transport to **email** (or a future SFTP/API driver) once a delivery
target exists, and completed batches are delivered on export.

## See also

- [Export templates](/docs/accounting/export-templates) - column layout
- [Accounting overview](/docs/accounting/overview)
