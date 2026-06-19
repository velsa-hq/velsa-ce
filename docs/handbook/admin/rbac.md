---
title: Roles & permissions (RBAC)
section: Admin
order: 40
surfaces:
  - route: /admin/roles
    method: GET
  - component: pages/admin/roles/index
  - route: /admin/roles/create
    method: GET
  - component: pages/admin/roles/form
  - route: /admin/permissions
    method: GET
  - component: pages/admin/permissions/index
  - route: /admin/users/{user}
    method: GET
  - component: pages/admin/users/show
  - route: /admin/users/{user}/permissions
    method: GET
  - component: pages/admin/users/permissions
tour_ids:
  - strong-rbac-create-role
  - strong-rbac-clone-role
  - strong-rbac-permissions-groups
  - strong-rbac-add-permission
  - strong-rbac-assign-role
  - strong-rbac-roles-list
---

Velsa's access control is **role-based and venue-scoped**: a user holds one or
more **roles**, each at a specific **venue**, and each role grants a set of
**permissions**. The County administers all of this in-app - no vendor
involvement for staff changes.

:::video strong-rbac

## Roles

**Admin -> Roles** lists the built-in roles (super admin, org admin, venue
admin, sales manager/rep, event coordinator, ops lead, finance, read-only,
exhibitor, contractor) plus any custom roles. Built-in roles are locked from
edit/delete so the baseline can't be broken.

- **Create** a custom role: pick permissions module-by-module (Bookings,
  Contracts, Accounting, Reporting, Admin, ...), each group with select-all/clear.
- **Clone** any role (built-in or custom): starts a new role pre-filled with the
  source's permissions - tweak and save as your own.
- **Delete** a custom role once no users hold it.

## Permissions

**Admin -> Permissions** lists every permission, which module it belongs to, and
how many roles/users hold it. You can **add a custom permission**
(`module.action`, e.g. `exports.run`) - it appears in the role builder and gates
any route or action that references it. Built-in permissions are reserved.

## Assigning roles to users

On a user's page (**Admin -> Users -> [user]**), assign a role **at a venue**, so
access is precise: "sales rep at the Conference Center, nothing elsewhere." Each
assignment can carry an optional **expiry date** - temporary access for shifts or
contractors. Expired assignments are removed automatically (checked hourly).

## Seeing effective access

**Admin -> Users -> [user] -> Permissions** shows a **venue × capability matrix** of
exactly what a user can do where, resolved across all their role assignments.

## Auditing

Every role/permission change - create, edit, delete, assign, unassign, expire -
is recorded in the **audit log**, and you can flag privilege changes with an
[audit rule](/docs/admin/audit-rules) (e.g. `role.`) for fast review.

## Multi-factor for privileged roles

Administrator and finance access can be required to use **multi-factor
authentication**; see the security settings.
