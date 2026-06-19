---
title: Insurance certificates
section: Admin
order: 70
surfaces:
  - /admin/insurance-certificates (pages/admin/insurance-certificates/index)
  - /admin/insurance-certificates/create (pages/admin/insurance-certificates/create)
  - /portal/insurance (pages/portal/insurance)
tour_ids:
  - coi-new
  - coi-status-filter
  - coi-review
  - coi-portal-upload
---

**Insurance certificate tracking** keeps every Certificate of Insurance
(COI) a client or exhibitor is required to carry in one place - with the
coverage details, the document itself, a review decision, and an expiry
date the system watches for you.

:::video coi-overview

## Why it matters

Venues require the parties using them to carry insurance - typically the
renting **client** (often naming the venue as an additional insured) and,
at trade shows, individual **exhibitors**. Letting a lapsed or missing
certificate slip through is a real liability. This feature makes the
status of every certificate visible and warns you before one expires.

## The certificate list

`/admin/insurance-certificates` lists every certificate with its holder
(the client or exhibitor it belongs to), policy type, carrier, coverage
amount, expiry date, and review status. Use the **status filter** to focus
on what needs action:

- **Pending** - submitted, awaiting your review.
- **Approved** - reviewed and accepted.
- **Rejected** - reviewed and declined (the holder needs to resubmit).
- **Expired** - past its expiry date; no longer valid.
- **Expiring soon** - approved but within the reminder window.

## Recording a certificate

Click **New certificate**, choose the **holder** (a client or an
exhibitor), enter the policy details - type, carrier, policy number,
coverage amount, effective and expiry dates - and attach the certificate
document (PDF or image). Staff-recorded certificates start as **Pending**
so they still pass through review.

## Reviewing

Open a certificate to see its details and document, then **Approve** or
**Reject** it, optionally with a note. The decision, who made it, and when
are recorded. Approving does not change the expiry date - the system still
watches it.

## Expiry watch

A nightly job does two things:

1. Marks any **Approved** certificate whose expiry date has passed as
   **Expired**.
2. Emails the configured compliance contacts about certificates expiring
   within the reminder window, so there's time to chase a renewal.

Both the window and the recipients are set under **Admin -> System settings
-> Compliance**:

- **Certificate expiry reminders** - turn the reminder email on or off.
- **Compliance contacts** - comma-separated addresses that receive the
  expiry digest. If empty, no email is sent (the list and statuses are
  still maintained).

## Exhibitor self-upload

Exhibitors can upload their own certificate from the portal at
`/portal/insurance` - they see the status of anything they've submitted
and can attach a new document. Self-uploaded certificates arrive as
**Pending** for staff to review, exactly like staff-recorded ones.
