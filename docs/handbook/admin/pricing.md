---
title: Pricing - rate cards & packages
section: Admin
order: 63
surfaces:
  - /admin/rate-cards (pages/admin/rate-cards/index)
  - /admin/rate-cards/create (pages/admin/rate-cards/create)
  - /admin/rate-packages (pages/admin/rate-packages/index)
  - /admin/rate-packages/create (pages/admin/rate-packages/create)
tour_ids:
  - rate-card-new
  - rate-card-list-filter
  - rate-card-edit-button
  - rate-package-new
  - rate-package-list-filter
  - rate-package-edit-button
---

Velsa manages pricing as **rate cards** (per-item price lists) and **packages**
(bundles sold at a single price). Both are **venue-scoped** and
**effective-dated**, so you can stage future pricing ahead of time and keep
superseded pricing on record.

:::video pricing-admin

## Rate cards

A **rate card** (`/admin/rate-cards`) is a price list for one venue, of one
**kind** - Standard, Nonprofit, Government, Member, Peak season, or Holiday - in
effect over a date window. Each card holds **entries**, and each entry prices
either a **space** or a piece of **equipment** by **unit**:

- **Hourly**, **Daily**, **Multi-day**, or **Time slot** for space rental
- a per-unit rate for equipment

Entries also support a **minimum charge** and **included hours** (e.g. "$1,200 a
day, includes 8 hours"). Create as many cards as you need - a Standard card and
a Nonprofit card for the same venue, a Peak-season card that takes effect on its
own date, and so on. Because cards are effective-dated, a future rate increase
can be entered now and it simply takes over when its effective date arrives.

## Packages & bundles

A **package** (`/admin/rate-packages`) is a named offering sold at **one bundled
price** - e.g. a "Wedding Package" or "Conference Day Bundle" - that includes
several components. Set the **package price**, then list the **included items**:
spaces, catalog equipment, and free-text services, each with a quantity. The
items document what the bundle contains; the price is the package as a whole.

Packages carry the same venue scoping, kind, and effective dating as rate cards.

## Who can manage pricing

Pricing is gated by the **`pricing.manage`** permission (view-only access via
`pricing.view`), so you can let, say, a sales manager maintain price lists
without granting broader admin rights. Every change is captured in the audit
trail.
