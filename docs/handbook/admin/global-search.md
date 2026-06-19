---
title: Global search
section: Admin
order: 76
---

The search bar in the top header (or **⌘K** / **Ctrl+K** anywhere)
opens a global palette that searches across every record type at
once - bookings, clients, exhibitors, invoices, contracts, venues,
spaces, and equipment. Type a few characters and you'll see grouped
results ranked by relevance.

## What gets indexed

Each indexed record contributes a small flat document to the search
engine:

| Type | Fields searched |
| --- | --- |
| Bookings | reference, name, kind, status, notes, client name, venue name |
| Clients | name, type, industry, source, notes |
| Exhibitors | company name, contact name, email, phone, booth assignment, event name |
| Invoices | number, status, source name (client / exhibitor), notes |
| Contracts | reference, kind, status, booking reference + name, client name |
| Venues | name, slug, city, state, summary |
| Spaces | name, kind, venue name |
| Equipment | SKU, name, description, category name |

Records are indexed automatically as they're created and updated.
Deleting a record removes it from the index. No nightly batch is
required.

## Using the palette

- **Open**: click the search bar in the header or press ⌘K / Ctrl+K.
- **Type**: results appear after a brief debounce (200 ms). Each
  group caps at 5 results so a high-volume type can't drown out the
  others.
- **Navigate**: ↑ / ↓ to move between results, Enter to open the
  highlighted one, Esc to close.
- **Click**: any result navigates to its detail page.

Each result shows a title, a subtitle (usually the key supporting
context - client name, venue, etc.), and an optional badge (status,
booth assignment, capacity).

## How it's wired

The application uses **Laravel Scout** as the search abstraction and
**Meilisearch** as the engine in production. In testing it falls
back to Scout's in-memory `collection` driver, which gives you a
real-search round-trip without needing Meilisearch running.

Configuration lives in [System settings](/docs/admin/system-settings)
under **Integrations -> Meilisearch (global search)**:

- **Meilisearch enabled** - master toggle. Off hides the search bar.
- **Host URL** - Meilisearch endpoint, e.g. `http://localhost:7700`
  for local dev or `https://your-meili.example.com` in production.
- **Master key** - Meilisearch master key (or a scoped API key).
  Encrypted at rest; write-only.
- **Index prefix** - prepended to every index name so multiple
  deployments sharing one Meilisearch instance don't collide.

## Running Meilisearch locally

For development:

```bash
# Docker - telemetry disabled
docker run -d --rm -p 7700:7700 \
  -e MEILI_NO_ANALYTICS=true \
  getmeili/meilisearch:latest

# Or the binary directly - telemetry disabled
meilisearch --no-analytics --db-path ./data.ms

# Or Homebrew (see telemetry note - brew services has no flag for it)
brew install meilisearch
brew services start meilisearch
```

Meilisearch defaults to `http://localhost:7700` with no master key in
dev mode - that matches the application's defaults out of the box, so
you don't need to set anything if the service is running.

> **Disable telemetry.** Meilisearch ships with anonymous usage
> analytics **enabled by default** - the server periodically pings
> `telemetry.meilisearch.com`. Always run it with `--no-analytics` (or
> env `MEILI_NO_ANALYTICS=true`); the Docker and binary commands above
> already do. `brew services` exposes no flag, so add
> `MEILI_NO_ANALYTICS=true` to the `EnvironmentVariables` of
> `~/Library/LaunchAgents/homebrew.mxcl.meilisearch.plist` and reload.
> **Production/hosted deployments must set `MEILI_NO_ANALYTICS=true` on
> the Meilisearch service** - treat it as a data-sovereignty requirement,
> not a preference.

## Initial / re-indexing

The first time you start using search on an existing database, run:

```bash
php artisan scout:import "App\Models\Booking"
php artisan scout:import "App\Models\Client"
php artisan scout:import "App\Models\Exhibitor"
php artisan scout:import "App\Models\Invoice"
php artisan scout:import "App\Models\Contract"
php artisan scout:import "App\Models\Venue"
php artisan scout:import "App\Models\Space"
php artisan scout:import "App\Models\EquipmentItem"
```

(Your administrator can do this once during deployment; users never
need to run it.) After that, the index stays current automatically.

To **rebuild** an index from scratch (e.g. if you changed the
searchable shape), use `scout:flush` followed by `scout:import` for
the affected model.

## What's not searchable (yet)

- **Booking narratives** - free-form text history entries on a
  booking. Indexable but currently excluded to keep the per-record
  payload small. May be added later as a separate group.
- **Work orders** - not yet on `Searchable`. Easy follow-up.
- **Leads** - same.
- **Audit log** - intentionally excluded; use the
  [audit page](/docs/admin/audit-log) for that.
- **Handbook articles** - same; use the in-app handbook search (also
  a future enhancement).
