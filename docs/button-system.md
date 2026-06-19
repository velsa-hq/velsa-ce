# Button color system

This app uses a small, consistent vocabulary of button treatments so a
user can spot the primary action on any screen at a glance. Everything
flows through the shadcn `<Button>` component at
`resources/js/components/ui/button.tsx`, which exposes a `variant` prop
that selects one of six treatments.

## Variants and when to use each

**Primary (`variant="default"`)** is the user's accent palette color
(Amber, Tomato, etc., per-user customizable). Reach for it for the
single most important action on a card or page - the thing the user
came here to do. Examples: Save, Send, Submit, Publish outline, Issue
invoice, "+ New booking", "+ Create user", form submits. There is
usually exactly one primary action per surface; if there are two, ask
yourself which one is the "happy path" and demote the other.

**Secondary (`variant="outline"`)** sits next to a primary, or stands
alone for actions that are useful but not the page's main goal.
Examples: Cancel, Close, Edit (when paired with a primary), Back,
Preview, Floor plan / Run-of-show links from a booking detail. Use
`outline` for filter / toolbar chips too - toggled chips can flip from
`outline` to `default` to show their active state.

**Destructive (`variant="destructive"`)** is fixed red regardless of
palette. Use it whenever the action loses data or cancels work that
has been committed: Delete, Remove, Void, Cancel booking, Decline,
Write off. The red is the warning, so don't soften it with a muted
"are you sure?" treatment elsewhere - let the button carry the weight.

**Tertiary inline (`variant="ghost" size="sm"`)** is for the small
row-level actions that cluster inside data tables: Edit, Cancel within
a popover, "Show source" toggles. The lack of fill keeps them quiet so
they don't compete with the page-level primary.

**Link (`variant="link"`)** is for genuine inline word-level actions
that read as part of a sentence - "or you can login using a recovery
code", "+ New client" inside a form fieldset. Reserve it for cases
where a button would feel too heavy.

**Secondary fill (`variant="secondary"`)** - muted grey - is rarely
the right answer in this app. Prefer `outline`. The component keeps it
in the API for cases where two adjacent non-primary actions need
different weights.

## Sizes

`default` is the page-level standard, `sm` is for in-toolbar / in-row
contexts, `lg` is for marketing-style hero actions (we don't really
have any), `icon` is for square icon-only buttons (modal close, etc.).

## Decision tree

Is this the page's primary action? -> `default`.
Is it next to a primary? -> `outline`.
Does it delete / void / cancel committed work? -> `destructive`.
Is it an inline row action inside a data table? -> `ghost size="sm"`.
Is it a filter / toggle chip? -> `outline size="sm"`, flip to `default`
when active.
Is it a word inside a sentence? -> `link`.

## What to leave alone

A handful of UI patterns aren't "action buttons" and should keep their
existing styling:

- Specialized canvas / diagram palette tools (the chair, wall, door
  picker in the diagram editor and the space constraints editor).
- Sidebar nav (`app-sidebar.tsx`) - has its own sidebar-accent system.
- Pure dropdown / popover / modal triggers (Radix triggers).
- Disclosure / accordion / tab triggers.
- Header sort / filter cells inside TanStack tables.
- Quick Links chiclets on the dashboard (`quick-links.tsx`) - their
  color is part of their semantics.
- Theme / palette picker swatches in `settings/appearance.tsx`.
- Form-input integrated controls (password show/hide eye, OTP slots).

If you're not sure: ask whether the user clicks this thing _to make
something happen_. If yes, it's an action button - pick the variant
above. If no, leave it.

## Applied app-wide

This convention was applied app-wide on 2026-05-30 in the
`feature/button-color-system` branch.

## Known follow-ups

The initial sweep was deliberately scoped to clear-cut action
buttons; a few adjacent surfaces want a second pass once the
convention has been stress-tested in production:

- **Dashboard "Customize" button** - the entry-point to the tile
  picker. Currently a plain outline; consider whether the
  page-level customization affordance reads as a primary or stays
  outline. Revisit after watching how often people actually click
  it.
- **Booking detail header (Edit / Floor plan / Run-of-show /
  Settlement PDF)** - all four are uniform outline today. They're
  doing slightly different jobs (Edit changes the record, Floor
  plan / Run-of-show open distinct workspaces, Settlement PDF is
  an export). A second pass could differentiate them, or promote
  one as the "primary next step" depending on the booking's state
  (e.g. Run-of-show primary when outline is unpublished but event
  is < 7 days out).
- **Toast color system** - success / error / warning / info toasts
  should carry the same color language as the buttons (success ≈
  the accent palette's primary, error ≈ destructive red, etc.).
  Right now they're all neutral, which makes "contract drafted"
  and "save failed" feel the same from the corner of the eye.
