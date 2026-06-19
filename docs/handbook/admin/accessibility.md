---
title: Accessibility (WCAG 2.1 AA)
section: Admin
order: 72
---

When deployed by a state or local-government entity, this application
falls under **ADA Title II** and the DOJ's 2024 rule requiring
**WCAG 2.1 Level AA** conformance. The compliance date for entities
with populations over 50,000 was **April 2026**; smaller entities had
until April 2027.

This page is the operating reference for what the application
guarantees, what's checked automatically during development, and what
needs periodic manual review.

## What's guaranteed

- **Static accessibility checks** run on every build, blocking changes
  that miss alt text, mis-associate form labels, use positive
  tabindex, leave anchors without an `href`, or misuse ARIA roles
- **Runtime accessibility checks** run during development so WCAG
  violations are caught before they reach production
- Production deployments never ship the development-only check tooling

## Patterns the application applies

- **Form labels** - every input, select, and textarea has an
  associated visible label or screen-reader label
- **Status filter chips** on every index page announce their pressed
  state so screen readers know which filter is active
- **Sortable table headers** announce their sort direction
- **Toggle controls** (e.g. activity mark-done) use proper switch
  semantics with descriptive labels
- **Decorative images** (sidebar logo) are skipped by screen readers
  since adjacent text already names them
- **Content images** (welcome banner, auth-page seal) have
  descriptive alternative text
- **Icon-only buttons** (calendar prev/next, sort arrows) carry an
  accessible name even though the icon itself is hidden from screen
  readers

## Manual-test checklist

Run this once per quarter and before any major release.

### Keyboard navigation

- [ ] Tab through every page from top to bottom - focus order matches
  visual order, every interactive element is reachable, no keyboard
  traps
- [ ] All buttons activate with Space and Enter
- [ ] Date-range picker opens with Enter on the trigger, closes with
  Escape, day picker navigable with arrow keys
- [ ] Dropdowns (Columns, status filters, etc.) open and close with
  the keyboard
- [ ] All forms can be filled in and submitted without a mouse

### Screen reader smoke tests

Use VoiceOver on macOS (Cmd+F5) or NVDA on Windows.

- [ ] Login page reads "Sign in to your account" landmark
- [ ] Each list page announces a useful page title
- [ ] Sortable columns announce "ascending" / "descending" / "none"
  when the sort changes
- [ ] Status filter chips announce "pressed" when active
- [ ] Form errors are announced after a failed submit
- [ ] Toast notifications are announced

### Visual

- [ ] Page is usable at 200% zoom (no horizontal scroll, no clipped
  content)
- [ ] Focus indicators visible on every interactive element
- [ ] Color contrast checked on status badges, button variants, and
  the brand palette

## Known gaps

- **Floor plan editor**: the canvas-based editor isn't introspectable
  by screen readers. A landmark region is announced, but there's no
  keyboard alternative for placing or moving objects. A future
  enhancement will add a focusable list of placed objects with arrow-
  key positioning.
- **Multi-month range picker**: keyboard support is good but doesn't
  announce range start/end transitions - users hear the new selected
  date but not "range start" vs "range end".
