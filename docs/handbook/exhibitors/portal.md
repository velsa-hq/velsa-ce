---
title: Exhibitor portal
section: Exhibitors
order: 59
---

The exhibitor portal is the external-user surface where exhibitors
self-serve - browse the equipment catalog, place orders, pay, and
download invoices. It's a separate authentication realm from staff
(no password, magic-link only) and runs under the `exhibitor` guard.

:::video exhibitor-portal

## URL space

| Path | Purpose |
| --- | --- |
| `/portal/access` | Public "email me a sign-in link" page |
| `/portal/login/{token}` | Magic-link landing (single-use) |
| `/portal` | Exhibitor dashboard |
| `/portal/catalog` | Equipment browse |
| `/portal/orders/{id}` | Order detail |
| `/portal/orders/{id}/pay` | BluePay payment page |
| `/portal/orders/{id}/invoice` | Invoice PDF download |
| `/portal/logout` | End the session |

## How an exhibitor gets in

Two paths, same magic-link mechanism:

**Staff-issued** - Staff visits `/exhibitors/{id}` -> **Issue portal
link**; the system generates a magic token and emails it to the
exhibitor's primary email.

**Self-service** - The exhibitor visits the public `/portal/access`
page (linked from the site header + footer), enters their email, and
gets the same link mailed to them. The page responds identically
whether or not the address is on file, so it can't be used to fish for
who's registered, and the endpoint is rate-limited.

Either way: the exhibitor clicks the link -> token verified -> session
created. The token is invalidated after first use and expires after 7
days regardless.

No passwords. No "forgot password" - request another link.

## What exhibitors can do

- View their order(s) and balance
- Add items from the equipment catalog (with quantity)
- Remove items from a pending order
- Pay (BluePay hosted iframe; PCI scope stays off our infrastructure)
- Download the invoice PDF

What they **can't** do:
- See other exhibitors' orders
- See internal pricing notes or admin-only items
- Access any admin URL (the `exhibitor` guard 404s out-of-tier routes)

## Security posture

- Guard is fully separate from the staff `web` guard - a staff user
  authenticated in `/admin` doesn't automatically have portal access
  and vice versa
- Sessions are short (default 2 hours) - re-auth requires a new
  magic link
- Exhibitor accounts are kept entirely separate from staff user
  accounts. This keeps staff permissions and exhibitor scope cleanly
  separated.
- Audit entries are still written for exhibitor actions but record
  the exhibitor identity rather than a staff user id

## What's coming

- Portal-issued refunds (today refunds run admin-only)
- Order print view + email-this-to-myself
- Multi-day order plans (per-day reservations rather than the full
  event window)
