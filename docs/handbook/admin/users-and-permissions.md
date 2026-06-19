---
title: Users & permissions
section: Admin
order: 70
---

User accounts and role assignments live under **Admin -> Users**
(`/admin/users`). Permissions are scoped to **venues** - a user can be
a sales manager at the main convention center but read-only at the
equestrian arena venue, and the system enforces that at every screen.

## How permissions are scoped

Every role assignment carries both a user and a venue. A user can
hold different roles at different venues - there's no single global
role (except `super_admin`, which has every permission everywhere).

When a user is logged in, the navigation and actions reflect their
combined permissions across all venues they have access to. Inside a
single venue's screens, only that venue's permissions apply.

## Roles

| Role | Typical user | Has |
| --- | --- | --- |
| `super_admin` | System owner | Every permission, every venue |
| `venue_admin` | Venue manager | All operations at one venue |
| `sales_manager` | Sales lead | Pipeline + bookings + contracts (view/edit/approve) |
| `sales` | Sales rep | Pipeline + bookings (view/create/edit) |
| `ops_lead` | Operations lead | Bookings + work orders + outlines (view/edit) |
| `ops` | Operations staff | Same as ops_lead, no edit on work orders |
| `accounting` | Accounting | Accounting + reports (view only) |
| `viewer` | Read-only stakeholder | View-only everywhere |

The 11 built-in roles above are seeded at install time and are
**locked from edit and delete**. To customize, create a new role
instead - see *Custom roles* below.

## Custom roles

`/admin/roles` lists every role in the system with badges for the
built-in 11 and a count of how many users hold each role.

### Creating a custom role

1. Click **New role** on `/admin/roles`
2. Enter a snake_case name (e.g. `venue_finance_admin`) - must be
   unique and not collide with a built-in
3. Tick the permissions you want; the picker is grouped by module
   (Venues + spaces, Bookings, Contracts, Sales, Exhibitors, Work
   orders, Accounting, Reporting, Admin) with a per-group **Select
   all** / **Clear** shortcut for quick composition
4. Save

### Editing

Open any custom role from the index to edit its name or permissions.
Built-in roles open read-only with a locked badge - the form is
disabled and the save button hidden.

### Deleting

The Delete button on a custom role's edit page (or the index Delete
action) refuses to fire when any user still holds the role - remove
the assignments first from `/admin/users/{id}` and then come back to
delete.

Every action writes an audit row (`role.created`, `role.updated`,
`role.deleted`) so the activity log shows who composed which role and
when.

## Granting access

The user index at `/admin/users` lists every account with its current
venue × role badges. A **New user** button opens a form to create a
local account - name, email, and a temporary password the user must
change at first sign-in; an initial venue × role is optional and can be
set later. (SSO users are auto-provisioned on first sign-in, so you only
need this for local accounts.) Click a row to open the user detail page
at `/admin/users/{id}`, where you can:

- **Edit profile** - change the name and email (with a uniqueness
  guard against the rest of the user table)
- **Disable an account** - provide a reason (required, shown in the
  audit log); locks the user out of all sign-ins. SSO users are not
  re-provisioned automatically while disabled.
- **Re-enable** - clears the disabled reason
- **Assign a venue × role pair** - pick a venue (or **All venues** for an
  organization-wide grant), pick a role, click Assign. A role held at every
  active venue collapses to a single "All venues" row. The `super_admin`
  role can only be granted by another super administrator.
- **Remove an existing assignment** - Remove button on each row of
  the assignment table (removing an "All venues" row revokes it everywhere)

Every action above writes an audit row tagged with the actor, the
event type (`user.profile_edited`, `user.disabled`, `user.enabled`,
`user.role_assigned`, `user.role_unassigned`), and the relevant venue
where applicable. You can see those rows in `/admin/audit` filtered
to subject type `User`.

A user with no role at a venue can't see that venue's data at all (the
venue won't appear in the venue dropdowns, and direct URLs return 404).

## Permission audit

Two read-only inspection views answer the questions that come up in
real audits.

### "Who can do X?"

`/admin/permissions` lists every permission grouped by module
(Venues + spaces, Bookings, Contracts, Sales, Exhibitors, Work orders,
Accounting, Reporting, Admin) with two columns:

- **Roles** - how many roles grant this permission
- **Users** - how many distinct users hold it (via any of those roles,
  at any venue)

Click a permission to drill into `/admin/permissions/{name}`. The
detail page shows:

- The list of roles that grant the permission
- A per-venue breakdown of who currently holds it and via which role,
  with each user linking back to `/admin/users/{id}`

### "What can user Y do?"

From the user detail page, click **View effective permissions ->** to
land on `/admin/users/{id}/permissions`. This is the user's
effective-permission matrix:

- A role-assignment summary table (venue -> which role(s) the user
  holds there)
- A permission matrix with permissions as rows, venues as columns, and
  a checkmark in each cell where the user has the permission at that
  venue

The matrix is computed as the **union** of every role the user holds
at each venue, so dual-role assignments at the same venue resolve
correctly. Only permissions the user holds at *some* venue are shown
- the full 38-permission catalog lives on `/admin/permissions`.

## Admin access is gated

Every screen under **Admin** enforces this catalog. A signed-in staff
member only reaches an admin area if they hold the matching permission at
some venue - `users.manage` for user management, `permissions.manage` for
roles + SSO mappings, `system.settings` for taxonomies + settings,
`audit.view` for the audit log, and so on. Without it the route returns
**403**, even though they're authenticated. (`super_admin` holds every
permission, so it reaches everything.)

## Disabling a user

Disabling a user from the user detail page takes effect immediately:
they can no longer sign in, and an already-signed-in session is ended on
their next request. Re-enable to restore access.

## Bootstrap super admin

On a fresh installation the first registered user is automatically
granted `super_admin` at every active venue. This bootstrap is
configured at deployment time so that someone always has the keys
without needing another super-admin to grant access first. Subsequent
users land roleless until promoted by an existing admin.
