---
title: System settings
section: Admin
order: 75
---

`/admin/system-settings` is the single admin page for every tunable
application-wide configuration value:

- **Branding** - logos, login-page imagery, app name
- **Defaults** - fiscal-year start month, measurement units (ft²/m²),
  idle-timeout duration, default export template
- **Integrations** - Microsoft Entra SSO + DocuSign + Postmark credentials,
  SSO provisioning policy + bootstrap admins

## Resolution order

When the application reads a setting, it consults three sources in
order and uses the first one that has a value:

1. **Database** - what an admin saved here
2. **Environment** - the deployment configuration (only when the
   setting declares an env-variable fallback)
3. **Registry default** - the value built into the application

Clearing a field in the admin UI **deletes the database row**, which
falls the value back through environment / default. Use this when
you want to revert to whatever was provisioned at deployment time.

## Branding

Lets you rebrand the entire application from one page, no asset
redeploy. The fields apply to the login page, the welcome page, and
the header logo throughout.

| Field | Where it appears |
| --- | --- |
| Application name | Browser tab, navigation, emails |
| Login-page title | Large title on the branded auth layout (e.g. your organization name) |
| Login-page subtitle | Second line under the title |
| Login-page tagline | Longer descriptive line at the bottom of the hero |
| Primary logo path | Logo shown on auth + welcome pages |
| Primary logo alt text | Screen-reader description of the logo |
| Login background image folder | Public folder containing stock images randomly chosen as the auth-page background |

For the logo + background folder, the values are paths relative to
the application's public root. Drop image files into the matching
folder, then point the setting at it. Supported formats: SVG, WebP,
PNG, JPG.

The login-background folder is glob-scanned for `.webp` files at
request time, and one is picked at random per session. To use a
different folder, change the setting (no app restart needed).

## Defaults

| Field | Purpose |
| --- | --- |
| Fiscal year start month | Month (1-12) the fiscal year begins. 10 = October. |
| Use metric units (m²) | Show and accept space areas in square metres instead of square feet. Stored values are unchanged - only display and entry switch. |
| Idle-timeout (minutes) | Automatically sign a user out after this many minutes of inactivity. 0 disables the timeout. |
| Exhibitor portal link lifetime (days) | How many days an exhibitor portal sign-in (magic) link stays valid before it expires. Shorter is more secure; default 7. |
| Default export template | Which export template is used for accounting batches when no template is explicitly chosen |
| Pipeline archive window (days) | How long after closing a Won/Lost opportunity it ages off the pipeline board into the archive. Future-dated events stay on the board. |
| Pipeline overdue grace (days) | How many days past its expected close date an open opportunity must be before the board flags it overdue. 0 = the day after. |
| Stale tentative-booking window (days) | Dashboard "Needs attention": days without narrative activity before a tentative booking is flagged as going cold. |
| Unopened-contract window (days) | Dashboard "Needs attention": days a sent contract can go unopened before it's flagged. |
| Stuck-lead window (days) | Dashboard "Needs attention": days an opportunity can sit in "contract sent" without movement before it's flagged. |
| Default dashboard tiles (new users) | Which tiles a user lands on before they customize their own dashboard. A checklist sourced from the live tile catalog. |

Stage **labels and default probabilities** are tuned on their own
page - see [Pipeline stages](/docs/admin/pipeline-stages).

## Integrations

Third-party service wiring lives here. The admin UI is the
recommended path; the equivalent environment variables remain as a
deployment-time fallback (useful for bootstrap before an admin
exists).

### SSO (master)

| Field | Purpose |
| --- | --- |
| SSO enabled | Master toggle for single-sign-on. When off, no SSO buttons appear regardless of per-provider config. |
| SSO provisioning mode | `jit` auto-creates users on first sign-in; `invite_only` refuses sign-ins for users not yet provisioned. |
| SSO default role | Role granted to newly-provisioned SSO users (when not on the bootstrap-admin list). |
| SSO bootstrap-admin role | Role granted on first sign-in to users on the bootstrap-admin list. |
| SSO bootstrap-admin emails | Comma-separated list of email addresses that receive the bootstrap-admin role on first sign-in. |

### Microsoft Entra SSO

| Field | Purpose |
| --- | --- |
| Microsoft SSO enabled | Show the "Sign in with Microsoft" button on the login page (in addition to the master SSO toggle). |
| Microsoft tenant ID | Directory (tenant) ID from the Entra app registration. |
| Microsoft client ID | Application (client) ID from the Entra app registration. |
| **Microsoft client secret** | Client-secret Value from Entra. Encrypted at rest; **write-only** from this page. |
| Microsoft redirect URI | Must exactly match the redirect URI registered on the Entra app. |

See [SSO setup (Microsoft Entra ID)](/docs/admin/sso-setup) for the
Entra-portal walkthrough.

### DocuSign

| Field | Purpose |
| --- | --- |
| DocuSign enabled | Swap the signature provider from the demo driver to live DocuSign. |
| DocuSign base URI | The REST endpoint base. |
| DocuSign OAuth base | The OAuth endpoint base. |
| DocuSign integration key | The Integration Key (Client ID) from DocuSign Admin. Encrypted at rest. |
| **DocuSign secret key** | The Secret Key paired with the Integration Key. Encrypted at rest; **write-only**. |
| **DocuSign Connect HMAC key** | Secret used to verify inbound Connect webhook signatures (DocuSign Admin -> Connect -> HMAC). **Required for production** - when set, unsigned/mismatched webhooks are rejected. Separate from the secret key. Encrypted at rest; **write-only**. |
| DocuSign keypair ID | The RSA keypair ID for the JWT integration grant. |
| DocuSign API user ID | The User ID DocuSign actions are performed as. |
| DocuSign account ID | The Account ID for the DocuSign tenant. |

### Email delivery

Routes every transactional message - refund notices, dunning notices,
payment receipts, account and support notifications - through the
provider you select. The **Email transport** dropdown is the single
control:

| Transport | What it does |
| --- | --- |
| Default (environment / log) | Falls back to whatever the environment configures - typically the `log` driver in dev, which writes messages to `storage/logs/laravel.log` instead of sending them. |
| Postmark | Sends via [Postmark](https://postmarkapp.com)'s API using the Server API token below. |
| Microsoft 365 | Sends via authenticated SMTP to `smtp.office365.com` using the mailbox credentials below - mail originates from the organization's own Microsoft 365 tenant, no third-party email vendor in the path. |

Fill in only the credentials for the transport you choose. The
**From name** shown in recipients' inboxes is sourced automatically from
**Login-page subtitle** (`branding.app_subtitle`) - no separate field
here to keep in sync.

#### Postmark fields

| Field | Purpose |
| --- | --- |
| **Server API token** | The per-server token from Postmark -> Servers -> API Tokens. Encrypted at rest; **write-only**. |
| From address | The verified sender. Must be either an individually verified Sender Signature in Postmark or sit on a DKIM-confirmed domain. Postmark recommends a real mailbox over `no-reply@...`. |

#### Microsoft 365 fields

| Field | Purpose |
| --- | --- |
| Username (mailbox) | The Microsoft 365 mailbox that authenticates to `smtp.office365.com`. **SMTP AUTH must be enabled** for this mailbox in the tenant. |
| **Password / app password** | Password or app password for the mailbox. Encrypted at rest; **write-only**. Tenants that have retired basic-auth SMTP should use an app password. |
| From address | Usually the same as the mailbox, or an address it is permitted to send as. |

> Microsoft is retiring basic-auth SMTP; an OAuth / Microsoft Graph
> integration (using the same Entra app-registration model as SSO) is the
> longer-term path and removes the SMTP password entirely.

**Setting up a Postmark account from scratch:**

1. Sign up at [postmarkapp.com](https://postmarkapp.com) and create a
   new **Server** (one per environment is the recommended pattern).
2. Either verify an individual **Sender Signature** (one mailbox at a
   time, quick) or add your **Sending Domain** with DKIM + Return-Path
   DNS records (verifies anything `@your-domain.com`, takes a few
   minutes to propagate).
3. Generate a **Server API Token** under the server's API Tokens tab.
   Paste it into the field above and select **Postmark** as the transport.

Treat any of these credentials as secrets - rotate them at the provider
if they leak.

### Payment gateway

Exhibitor and booking payments run through a single active gateway,
chosen with the **Active payment gateway** dropdown. Only one gateway is
live at a time, and selecting one reveals just its credential fields.

| Gateway | What it does |
| --- | --- |
| None (environment / safe stub) | Leaves the environment default in place - a safe stub that never charges a live card. Use in non-production. |
| CardPointe (Fiserv / CardConnect) | Processes payments through the CardPointe Gateway API. This is the current Fiserv gateway; Fiserv is consolidating the legacy BluePay gateway onto CardPointe. |
| BluePay (Fiserv, legacy) | Processes payments through the legacy BluePay Post (bp10emu) API. |
| Stripe | Processes payments through the Stripe API. |

#### CardPointe fields

| Field | Purpose |
| --- | --- |
| Site | Your CardConnect site name - the subdomain in your CardPointe URL (e.g. `fts` in `fts.cardconnect.com`). Provided by CardConnect. |
| Merchant ID (merchid) | The CardPointe merchant ID for the County's merchant account. |
| API username | The CardPointe Gateway API username used for HTTP Basic authentication. |
| **API password** | The CardPointe Gateway API password. Encrypted at rest; **write-only**. |
| Environment | `UAT` runs against the CardConnect test sandbox; `Production` moves funds. Stay on UAT until the merchant account is verified end to end. |

#### BluePay fields

| Field | Purpose |
| --- | --- |
| Merchant (Account) ID | The 12-digit BluePay Gateway Account ID. |
| **Secret key** | The account Secret Key used to compute the TAMPER_PROOF_SEAL. Encrypted at rest; **write-only**. |
| Mode | `Test` runs simulated transactions; `Live` moves funds. Stay on Test until the merchant account is verified end to end. |
| TPS hash type | The hash algorithm for the TAMPER_PROOF_SEAL. Must match the account's "Hash Type in APIs" setting; HMAC_SHA512 is the modern default. |

#### Stripe fields

| Field | Purpose |
| --- | --- |
| **Secret key** | The Stripe secret API key (`sk_...`). Encrypted at rest; **write-only**. |
| Currency | ISO currency code for charges (e.g. `usd`). |

> **Switching gateways is significant.** Stored payment tokens are
> gateway-specific: a token saved under one gateway does not work on
> another. The dropdown warns before you change away from a configured
> gateway. Plan any switch deliberately - saved cards and scheduled
> charges must be re-collected on the new gateway.

## Secrets

Sensitive values are **encrypted at rest** using the application's
encryption key. In the admin UI they display as a masked placeholder
(`••••••••`); leaving the field unchanged keeps the existing value.
Typing a new value overwrites it.

Secret values are **never shipped to the browser in clear**. The
admin page receives only the masked placeholder; the actual cleartext
lives in encrypted form in the database and is decrypted only when
the application internally needs it (e.g. when redirecting to the
Entra OAuth endpoint).

A **Show** button toggles plaintext display while you're typing - use
sparingly. It only works on values you're actively typing, never on
stored values.

## Audit

Every change writes an audit entry. Filter `/admin/audit` on the
system-settings event type to see who changed what. Secret values are
redacted from the audit payload - the entry records that the setting
changed but not the new value.

## Cache invalidation

Reads are cached for performance. Writes invalidate the cache
automatically, so changes are visible on the next page load - no
manual refresh or app restart needed.

## Bootstrap order

When you're setting up a brand-new deployment, the typical order is:

1. Deploy with bootstrap admin emails set via environment
   (`SSO_BOOTSTRAP_ADMIN_EMAILS`) so the first SSO sign-in lands as
   `super_admin`
2. Sign in as the bootstrap admin
3. Move every other integration credential into the admin UI (so
   future rotations don't require a redeploy)
4. Optionally clear the environment values once the database has its
   replacements - the resolution-order rules ensure the DB values win

## What's not yet under system settings

A few values still live in the deployment configuration:

- GL system tenant + credentials

These migrate in follow-up passes once their integrations have a
real production tenant to test against.
