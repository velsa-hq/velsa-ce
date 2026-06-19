---
title: Layout templates
section: Admin
order: 78
surfaces:
  - route: /admin/layout-templates
    method: GET
  - route: /admin/layout-templates/{layoutTemplate}
    method: DELETE
  - route: /diagrams/{diagram}/apply-template/{layoutTemplate}
    method: POST
  - route: /diagrams/{diagram}/save-as-template
    method: POST
  - component: admin/layout-templates/index
  - component: bookings/diagram
tour_ids:
  - admin-lt-delete
  - diagram-apply-template
  - diagram-save-as-template
---

A **layout template** is a saved arrangement of furniture, booths,
and stages - banquet rounds, classroom rows, U-shape, trade-show
booth grid, cocktail reception, and so on. Apply a template to a
booking's floor plan and the system drops the saved objects onto
the canvas; you tweak from there.

Two surfaces use them:

- **Admin browse / cleanup** at `/admin/layout-templates`
- **Apply + save-as** inside the [floor plan editor](/docs/diagrams)
  on a booking

## Scope: global vs space-scoped

- **Global** (`space_id = NULL`) - appears as a candidate in every
  space's editor. The default starter set ships here (the seeder
  names them `Banquet rounds - 80 guests`, `Banquet rounds - 240
  guests`, `Classroom - 60 students`, `U-shape boardroom - 20
  seats`, plus a 6×6 booth grid and a cocktail reception).
- **Space-scoped** (`space_id` set) - only surfaces in that
  specific space's editor. Use when a layout only makes sense for
  one room (e.g. a custom 6×6 booth grid that fits the Coral Reef
  Grand Ballroom exactly but would spill walls in a smaller space).

The picker in the booking diagram shows global templates first
(with a "global" chip), then any space-specific ones.

## Admin index

The table at `/admin/layout-templates` lists every template with
name, category (banquet / classroom / theater / booth_grid / u_shape
/ reception / other), scope (Global or the specific space),
object count, total seat count, created-by, and updated date.

Click **Delete** on a row to remove a template. Deletion only
affects the template itself - booking diagrams that previously
applied the template keep their objects, since apply-template is a
clone-on-apply operation (the diagram now owns its own copy of the
objects). The confirmation is a simple "Delete template X?" - no
usage count surfaces here, because templates aren't joined to
contracts or any other usage record.

## Creating a template

Templates are created from the floor-plan editor on a real booking,
not from the admin page. Workflow:

1. Open a booking's floor plan at `/bookings/{id}/diagram`.
2. Build the layout (drag objects from the library, position
   them, label them).
3. Click **Save as template** in the header.
4. Fill in name (required), category (banquet / classroom / etc.),
   and an optional description.
5. **Save template** - the template is created scoped to the
   diagram's space.

To make it global afterwards, edit it in... wait - there's no admin
edit form yet (templates are read-only from the admin index;
delete + recreate is the workflow). Most teams don't need to
re-edit templates frequently; if you do, raise the issue and we'll
add an edit form.

## Applying a template

In the floor-plan editor, click **Apply template...** in the header.
A picker drops down with every available template for the diagram's
space - global templates first, then any space-specific ones. (A
single template row carries one `space_id`, so it can't appear in
both scopes; the picker just unions the two queries.) Each card
shows the template name, object count, seat count, category, and
a description.

Each card has two buttons depending on whether the canvas is empty:

- **Apply** (empty canvas) / **Replace** (canvas has objects) -
  swap the entire canvas for the template's objects
- **Append** (only when canvas has objects) - add the template's
  objects on top of what's already there

Applied objects get fresh IDs each time so the same template can be
applied twice without ID collisions, and the cloned objects are
fully editable - drag, delete, edit properties, re-save as a new
template.

## See also

- [Floor plan editor](/docs/diagrams) - where templates are
  applied and built
- [Space constraints](/docs/admin/space-constraints) - the
  permanent infrastructure that templates render against
