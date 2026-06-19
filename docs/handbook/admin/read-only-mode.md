---
title: Read-only mode
section: Admin
order: 69
surfaces:
  - route: /admin/system-settings
    method: GET
  - route: /admin/system-settings
    method: PUT
  - component: admin/system-settings/index
---

**Read-only mode** temporarily freezes all changes across Velsa - used
during a data import, a backup, or any maintenance window where you don't
want the dataset shifting underneath you. Turn it on and off under **Admin
-> System settings -> Read-only mode**.

## What it does

While read-only mode is on, **mutating actions are blocked** - creating,
editing, or deleting anything returns a "Velsa is in read-only mode"
notice and changes nothing. A red **Read-only mode** ribbon shows at the
top of every page so it's obvious why a save bounced.

**Reading is unaffected** - browsing, searching, opening records, and
running/exporting reports all work normally.

## What still works (by design)

A short allowlist keeps the essentials usable so you're never locked out:

- **Signing in and out.**
- **Turning read-only mode back off** (this settings page).
- **The data importer** - read-only mode is meant to be turned on *for* an
  import, so imports run while it's active.
- **External webhooks** (DocuSign, payment callbacks) - these are
  server-to-server and aren't interactive edits.

## Scope + limits (be honest about these)

Read-only mode blocks **interactive** changes - things people do in the
browser. It does **not** pause **background jobs** (the nightly dunning,
hold-expiry, and scheduled-report jobs) or queued work. If you need a true
total freeze for a migration, also pause the queue/scheduler at the
infrastructure level.

## Relationship to imports

High-risk imports - **bookings** and other bulk/financial kinds - *require*
read-only mode before they'll commit, because they change the live dataset.
The import won't let you commit until it's on, and the import job records
that read-only covered the run, which is what makes a later
[reversal](/docs/admin/data-import) safe (nothing else could have changed in
between). Low-risk kinds (clients, chart of accounts) don't require it.

## Read-only vs. Demo/safe mode

These are different. **Read-only** is a *temporary* freeze on a normal
(live) instance. **Demo/Training (safe mode)** is a permanent property of a
*non-acting* instance - it stays fully editable but never sends real
emails, e-signatures, or charges. A practice instance can be both.
