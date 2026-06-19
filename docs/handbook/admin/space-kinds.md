---
title: Space kinds
section: Admin
order: 64
surfaces:
  - route: /admin/space-kinds
    method: GET
  - route: /admin/space-kinds
    method: POST
  - route: /admin/space-kinds/{spaceKind}
    method: PUT
  - route: /admin/space-kinds/{spaceKind}
    method: DELETE
  - component: admin/space-kinds/index
tour_ids:
  - nav-space-kinds
  - space-kind-label
  - space-kind-add
  - space-kind-hide
  - space-kind-move-up
  - space-kind-move-down
---

**Space kinds** are the taxonomy used to classify every space - room,
ballroom, outdoor field, arena, and so on. The list is **user-definable**:
admins manage it at `/admin/space-kinds` instead of it being hardcoded.

It feeds two places:

- the **Kind** dropdown when [creating or editing a space](/docs/venues)
- the **Kind** filter on [Find a space](/docs/find-a-space)

:::video manage-space-kinds

## The list

Each kind has:

- a **label** - the human name shown in dropdowns (e.g. "RV Pad")
- a **key** - an auto-generated slug stored on spaces (e.g. `rv_pad`);
  immutable once created so existing spaces keep their classification
- an **order** - controls where it sits in dropdowns (set with the
  up/down arrows)
- a **shown/hidden** state - hidden kinds stay on existing spaces but
  drop out of the pickers
- an **in-use** count - how many spaces currently use the kind
- a **system** badge on the seeded defaults

## Adding a kind

Type a label (e.g. "Chapel") and click **Add kind**. The key is
derived from the label (`chapel`). If a kind with the same key already
exists, the add is rejected - pick a distinct name.

## Renaming

Edit a row's label and click **Save**. The key can't be changed (it's
the value stored on spaces), and renaming never changes visibility or
order.

## Hiding (the easy way to curate the defaults)

Click **Hide** on any kind to drop it from the space form + Find-a-space
filter; the row dims and shows a "hidden" badge, and the button flips to
**Show**. Hidden kinds stay on any spaces already using them - nothing
is lost. This is the intended way for an org to trim defaults it doesn't
use (a downtown conference center hiding **Barn**, **Stall**, and **RV
Pad**, say) without deleting anything. System kinds can be hidden too.

## Reordering

Use the **↑ / ↓** arrows to move a kind up or down; the order is what
the Kind dropdowns and the finder filter use.

## Deleting

A kind can only be deleted when it's **not a system kind** and **not in
use** by any space. Otherwise the Delete control shows a dash:

- **System kinds** (the seeded defaults) can't be deleted - **Hide** one
  instead.
- A kind **in use** must have its spaces reassigned to another kind
  first.

## Why a managed list (vs. a fixed enum)

A single deployment is well served by the curated defaults, but venues
differ - a fairgrounds needs stalls and RV pads, a performing-arts
center wants stages and galleries. Making the taxonomy editable lets
each deployment shape it without a code change, while the `is_system`
defaults guarantee a stable baseline for seed data and reporting.
