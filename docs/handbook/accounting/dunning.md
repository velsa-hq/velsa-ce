---
title: Dunning (past-due escalation)
section: Accounting
order: 49
---

Dunning is the cadence of reminders sent to a customer with a
past-due invoice. The system runs the schedule automatically; you
only configure the timing if the defaults don't match your policy.

## Stages

| Stage | Triggers when | Default message |
| --- | --- | --- |
| `none` | Invoice not past due | (no email) |
| `friendly_reminder` | 7 days past due | "Just a heads-up your balance is now past due..." |
| `firm_notice` | 21 days past due | "Your invoice is now 21 days past due; payment is requested..." |
| `final_notice` | 45 days past due | "FINAL NOTICE - balance must be paid within 10 days or..." |

Each invoice tracks the most recent dunning stage escalated to; the
badge appears next to the status badge on the invoice show page.

## How the cadence runs

A nightly background job scans every Issued, Partial-paid, or
Past-due invoice and:

1. Recomputes whether the invoice is past due against its due date
2. Determines the appropriate stage based on days-past-due
3. If the stage has changed, advances the dunning stage and sends a
   notice to the source's primary contact
4. Writes an audit entry

The job is idempotent - running it twice on the same day doesn't send
duplicate notices.

## Manual override

From `/admin/invoices/{number}`:

- **Reset dunning** - clears the stage so the cadence starts fresh.
  Useful when you've spoken to the customer and don't want the next-
  stage email to go out tomorrow.
- **Mark as paid** - applying a payment that zeros the balance
  automatically resets dunning.

## Statements

The customer statement at `/admin/exhibitors/{exhibitor}/statement`
(or `/admin/clients/{client}/statement`) renders aging buckets at the
top - current / 1-30 / 31-60 / 61-90 / 90+ - which is what your
finance team should reconcile against during AR review.
