---
title: SSO setup (Microsoft Entra ID)
section: Admin
order: 73
---

Velsa supports single-sign-on via Microsoft Entra ID (OIDC). When
enabled, the "Sign in with Microsoft" button on the login page hands
the user off to your tenant's login flow; on success the user lands
on the dashboard with a session, and an audit entry records the
sign-in.

This page walks through the one-time setup. The credentials below are
sensitive - they're delivered to the application's deployment
configuration and are never stored in the project's source.

## 1. Register the app in Entra

Sign in to [entra.microsoft.com](https://entra.microsoft.com) as a
Global Administrator (or someone with App registration rights).

1. **Identity -> Applications -> App registrations -> New registration**
2. **Name**: `Velsa` (or whatever you'd like users to see on
   the consent screen).
3. **Supported account types**: `Accounts in this organizational
   directory only (single tenant)` for most deployments. Choose
   multi-tenant only if you intend to accept sign-ins from other
   tenants.
4. **Redirect URI**: `Web` ->
   `https://{your-host}/auth/sso/microsoft/callback`
5. Click **Register**.

You'll land on the app's **Overview** page. Note these two values for
the next step:

- **Application (client) ID**
- **Directory (tenant) ID**

## 2. Create a client secret

1. From the app page: **Certificates & secrets -> Client secrets -> New
   client secret**.
2. Description: `Velsa prod` (or `dev`, etc.).
3. Expires: 12 or 24 months. Calendar this - Entra will not warn you
   before expiry, and a stale secret silently breaks sign-in.
4. **Copy the Value immediately.** Entra only shows it once.

## 3. Configure permissions

`User.Read` (delegated) is enough for sign-in and to read the user's
profile and email. No admin consent is required.

**`GroupMember.Read.All`** is also requested by default - the app
uses it to fetch the user's Entra group memberships and apply the
mappings configured at `/admin/sso-mappings`. This permission requires
**admin consent** on the app registration. From Entra -> Your app ->
API permissions, click **Grant admin consent for [tenant]**. Until
consent is granted, group-driven role assignment silently no-ops and
sign-in still succeeds; the audit row will show
`entra_groups_applied: 0`.

## 4. Save the values in System settings

Sign in as an administrator and open
[System settings](/docs/admin/system-settings) -> **Integrations**.
Paste in:

- **Microsoft tenant ID** (from step 1)
- **Microsoft client ID** (from step 1)
- **Microsoft client secret** (from step 2 - encrypted at rest,
  write-only from this page going forward)
- **Microsoft redirect URI** - must exactly match the URI registered
  on the Entra app
- **Microsoft SSO enabled** - flip to On
- **SSO enabled** (master toggle) - flip to On

Also configure the provisioning posture in the same panel:

- **Provisioning mode**: `jit` (auto-create users on first sign-in)
  or `invite_only` (refuse sign-ins for users not yet provisioned)
- **Default role**: role new users land on (e.g. `read_only`)
- **Bootstrap admins**: comma-separated list of emails that should
  be granted `super_admin` on first sign-in

**Save**. Changes are live on the next page load - no app restart
needed.

Alternative: for bootstrap deployments before an admin exists, the
same values can be passed via environment variables (`SSO_ENABLED`,
`MICROSOFT_TENANT_ID`, `MICROSOFT_CLIENT_ID`, `MICROSOFT_CLIENT_SECRET`,
`MICROSOFT_REDIRECT_URI`, `SSO_BOOTSTRAP_ADMIN_EMAILS`). Once an admin
signs in via the bootstrap path, prefer moving them into System
settings so rotations don't require a redeploy.

## 5. First sign-in

Open the login page in a private window. The "Sign in with Microsoft"
button should appear above the email/password form. Click it; you'll
be redirected to Entra, prompted for consent on first run, then sent
back to the dashboard.

If your email was on the bootstrap-admin list, you're granted
`super_admin` at every active venue. Subsequent users land with the
configured default role until an admin promotes them via the users
UI.

## Provisioning modes

| Mode | Behavior |
| --- | --- |
| `jit` | Auto-create the user on first sign-in. Default role applies; bootstrap-admin emails get the elevated role. |
| `invite_only` | First sign-in fails unless an admin has already invited the user (creating a row with a matching email). On match, the user is permanently linked to their identity provider so future sign-ins skip the email lookup. |

For a POC, `jit` is fine. For a production deployment with strict
access requirements, switch to `invite_only` and invite users
explicitly.

## Troubleshooting

- **The Microsoft button doesn't appear.** Confirm SSO is enabled
  and the Microsoft provider is configured. Have your administrator
  restart the application after configuration changes.
- **Entra returns "AADSTS50011: redirect URI mismatch".** The redirect
  registered on the app must match the application's configured
  redirect URI exactly - same scheme, host, port, and path.
- **"Sign-in failed. Please try again."** Check the application log
  - usually an expired client secret or a scope that requires admin
  consent.
- **First user lands with no role.** Either the role configuration
  hasn't been seeded or there are no active venues yet. Create a
  venue, then sign in again.

## Audit trail

Every SSO sign-in writes an audit entry with the provider, the
immutable provider id, the email, and whether the user was newly
provisioned. Filter the admin -> audit page on the SSO event type to
see the history.
