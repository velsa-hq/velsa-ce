---
title: Audit rules
section: Admin
order: 72
surfaces:
  - route: /admin/audit-rules
    method: GET
  - component: pages/admin/audit-rules/index
  - component: pages/admin/audit/index
tour_ids:
  - audit-rules-name
  - audit-rules-event-type
  - audit-rules-description
  - audit-rules-add
  - audit-rules-toggle
  - audit-rules-remove
---

**Audit rules** let you define your own watch list of activity to highlight in
the audit log. Each rule is an **event-type prefix**; any audit event whose type
starts with that prefix is **flagged**.

:::video audit-rules

## Defining rules

Under **Admin -> Audit rules**, add a rule with a name and an **event-type
prefix** - for example `role.` to flag every privilege change, `user.disabled`
for account lockouts, or `invoice.` for billing activity. Toggle a rule
**Active/Inactive** without deleting it, or remove it entirely. Managing rules
needs audit access.

## Seeing flagged activity

On the **Audit log** (Admin -> Audit), events that match an active rule show a
**⚑ flagged** marker, and the **"Flagged only"** filter narrows the log to just
those events - a fast way to review sensitive activity (privilege changes,
disabled accounts, refunds) without scrolling the whole trail.
