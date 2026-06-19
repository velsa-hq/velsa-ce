---
title: Error pages
section: System
order: 95
surfaces:
  - errors/error (pages/errors/error) - the shared branded error screen
tour_ids: []
---

# Error pages

When something goes wrong, Velsa shows a single branded error screen instead
of a raw server page. It's deliberately self-contained - it doesn't depend on
your organization's settings or the database - so it still renders cleanly even
when the underlying problem is the database or a failed boot.

Each HTTP status gets plain-language copy and the right next step:

| Code | Heading | What it means | Action shown |
| --- | --- | --- | --- |
| 403 | Access denied | You're signed in but don't have permission for this area. | Back to dashboard |
| 404 | Not found | The page or record doesn't exist (or was removed). | Back to dashboard |
| 419 | Session expired | You were idle too long, or the page sat open. | Refresh / sign in |
| 429 | Too many requests | Rate-limited after repeated attempts (e.g. login). | Wait and retry |
| 500 | Something went wrong | An unexpected server error - it's been logged. | Back to dashboard |
| 503 | Down for maintenance | The instance is briefly unavailable or in maintenance/safe mode. | Try again shortly |

Notes:

- **403** is what you'll see if you reach an area your role doesn't grant (the
  role/permission model decides this) - ask an administrator if you believe you
  should have access.
- **503** is also what a [safe-mode](admin/safe-mode.md) demo/training instance
  shows during a maintenance window.
- In local development the detailed debugger is shown instead, so engineers see
  the stack trace.
- Sensitive details are never shown to the user; the specifics are logged
  server-side only.
