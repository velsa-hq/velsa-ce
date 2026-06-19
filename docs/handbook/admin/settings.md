---
title: Your account settings
section: Admin
order: 74
---

Per-user settings live under `/settings`. System-level configuration
(DocuSign credentials, BluePay merchant keys, SSO settings, default
export template, fiscal-year start, etc.) is delivered to the
application during deployment - a dedicated admin settings panel for
those is on the roadmap.

## Profile (`/settings/profile`)

Your display name, email, and avatar. Every edit appears on your
audit timeline.

Note: if you signed in via SSO, the email comes from your Entra
profile and re-syncs on every sign-in. Local edits to email persist
until your next SSO round-trip, at which point Entra's value wins.

## Appearance (`/settings/appearance`)

- **Theme** - Light / Dark / System
- **Density** - Comfortable / Compact
- Persists in a cookie so it survives across devices once you sign in

## Security (`/settings/security`)

Password + two-factor + passkey management.

- **Change password** - required for accounts that aren't SSO-only
- **Two-factor authentication** - TOTP via your authenticator app of
  choice (Google Authenticator, Authy, 1Password, etc.). Generates
  recovery codes you should print + lock away.
- **Passkeys** - register a passkey (WebAuthn) on this device for a
  passwordless sign-in path. Multiple passkeys per account supported.
- **Active sessions** - see every browser currently signed in to your
  account; revoke any except the one you're using right now
- **Disable account** - soft-disable (admin-only equivalent lives on
  the users admin page)

## What's not under /settings (yet)

System-level configuration - the things every deployment tunes - is
managed through the application's deployment configuration today:

- DocuSign credentials (sandbox vs production)
- BluePay merchant credentials (sandbox vs production)
- SSO tenant, client id / secret, and bootstrap admin list
- GL system tenant + credentials
- Default export template selection
- Fiscal year start month
- Idle-timeout duration
- Branding (logo, favicon, login background images)

A unified **System settings** admin panel that surfaces all of these
behind permission-gated forms is on the backlog. Until then, contact
your administrator to change any of the items above.

## Audit

Every settings change writes an audit entry. Filter `/admin/audit` by
event type or by your own user id to see the timeline of changes
against your account.
