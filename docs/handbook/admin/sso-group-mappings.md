---
title: SSO group mappings
section: Admin
order: 22
---

When a user signs in via Microsoft Entra, we look up their Entra
group memberships and assign Velsa roles based on the mappings
configured at `/admin/sso-mappings`. One mapping = one
(Entra group, role, venue) tuple. A blank venue means "every active
venue."

## Setting up a mapping

1. Open `/admin/sso-mappings` and click **New mapping**
2. Paste the **Group object ID** (GUID) from Entra. Find it in the
   Entra admin centre under Groups -> [your group] -> Overview ->
   Object ID.
3. Optionally name the group with a **Display label** - this is what
   shows in the mappings list so admins can scan by name rather than
   GUID. It doesn't have to match the Entra display name exactly;
   it's a local-side convenience.
4. Pick a **Role** from the dropdown (the 11 built-in roles plus any
   custom roles created at `/admin/roles`)
5. Pick a **Venue** - or leave it blank to apply at every active venue
6. Save

## How sign-in uses these

On every Microsoft SSO sign-in (not just first-time provisioning):

1. Microsoft returns an OAuth access token
2. We call MS Graph `/me/memberOf` to fetch the user's group GUIDs
3. We query the mappings table for any row whose `entra_group_id`
   matches one of the user's groups
4. For each matching row, we assign the role at the venue (or fan
   out across all active venues if `venue_id` is null)

The `auth.sso_login` audit row carries an `entra_groups_applied`
count so you can see at a glance how many mapped roles flowed through
on that sign-in.

## Required Entra permission

`GroupMember.Read.All` must be granted on the app registration, with
**admin consent**. See the [SSO setup guide](/docs/admin/sso-setup).
If consent isn't granted, MS Graph returns 403, the resolver
no-ops, and sign-in continues normally - the user just doesn't get
any mapped roles applied.

## Current limitation: additive only

Today the resolver only **adds** roles. Removing a user from an
Entra group does **not** revoke their role at the next sign-in. If
someone needs to lose access:

- Remove them from the group in Entra (so they don't pick up the
  role again next time their session is provisioned)
- Open `/admin/users/{id}` and revoke their existing venue × role
  assignments

A future enhancement (tracked in the backlog) will add a tracking
table for SSO-managed assignments so the resolver can detect and
revoke obsolete ones on each sign-in.

## Audit trail

Every mutation writes a row to `/admin/audit`:

| Event | Fires when |
| --- | --- |
| `sso_mapping.created` | A new mapping is added |
| `sso_mapping.updated` | Group label, role, or venue is changed (carries before/after) |
| `sso_mapping.deleted` | A mapping is removed |
| `auth.sso_login` | Any SSO sign-in; payload includes `entra_groups_applied` count |
