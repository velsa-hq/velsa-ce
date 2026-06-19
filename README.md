# Velsa

A modern, source-available event-management platform for venue
operators, event teams, and the agencies that serve them. Covers the
end-to-end lifecycle: sales pipeline, venue + space scheduling,
contracts + e-signature, exhibitor orders + portal, payments + AR,
fund accounting, and operations (work orders, run-of-show, floor
plans). Cloud-native, single-tenant with multi-venue support, and built for
government + enterprise deployment from day one.

Built and maintained by
[Palladium Innovations, LLC](https://www.go-palladium.com).

## Stack

- **PHP 8.5** + **Laravel 13** (backend, APIs, Eloquent)
- **Inertia.js v3** + **React 19** + **Tailwind v4** (frontend)
- **PostgreSQL** (production and tests)
- **Pest v4** + **PHPUnit 12** + **Larastan** + **Pint** (quality)
- **Vite** (bundling), **Wayfinder** (typed routes)

## Getting started

Prerequisites:

- PHP 8.5 (recommended via [Laravel Herd](https://herd.laravel.com))
- Composer 2.x
- Node 22 (recommended via nvm)
- PostgreSQL 16+ (any local install works; the project ships with a
  reasonable defaults config)

Clone and install:

```bash
git clone https://github.com/velsa-hq/velsa-ce.git
cd velsa-ce

cp .env.example .env
php artisan key:generate

composer install
npm install

php artisan migrate --seed
npm run build
```

Open the dev server:

```bash
composer run dev
```

The application is available at the URL Herd assigned (or whatever
your local `APP_URL` resolves to).

### Demo data

`php artisan migrate --seed` seeds the Sentinel Bay County, CA demo
dataset: a coastal county with a convention center, performing arts
hall, sports complex, fairgrounds, and lakeside retreat, plus ~14 named
staff, ~22 clients spanning hotels / casino / military / university /
government, and ~25 bookings across every status. The naming is stable
across runs so the demo and tests don't drift.

## Configuration

System-level configuration lives in two places:

- `.env` - credentials and bootstrap settings (DocuSign, SSO,
  database, mail). Gitignored.
- **Admin -> System settings** (`/admin/system-settings`) - runtime
  branding, defaults, and integration credentials. Settings here
  override the corresponding env values; clearing a field falls back
  to the env default.

See the **System settings** + **SSO setup** pages in the in-app
handbook for the full configuration walkthrough.

## Documentation

- **Development**: [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) - the local
  dev loop, the quality checks, and how to run the test suite.
- **In-app handbook**: `/docs` - operating manual for staff, admins,
  and integrators. Lives as markdown under `docs/handbook/`.
- **Contributing**: [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md)

## Quality + security

- `composer run analyze` - static analysis (Larastan)
- `vendor/bin/pint` - code formatting
- `php artisan test` - full test suite (Pest)
- `composer run dev` - local dev server
- GitHub Actions: Gitleaks (secret scan), Semgrep (SAST), Dependabot

## License

Velsa is **source-available** software under the Velsa
Source-Available License v1.0. You may install, run, and modify it
for your own internal business operations or non-commercial use;
offering it as a hosted or paid service to third parties requires a
separate commercial license.

Full license text: [LICENSE](LICENSE).
In-app: `/docs/license`.
Commercial licensing inquiries:
[sales@go-palladium.com](mailto:sales@go-palladium.com).

Copyright © 2026 Palladium Innovations, LLC. All rights reserved.
