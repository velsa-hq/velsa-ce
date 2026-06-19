# Development

How the Velsa app is developed and checked locally, and what CI enforces.

## The loop

```
  change --> tests + handbook --> run the checks --> open a PR --> CI
```

1. **Make the change.** For user-facing surfaces, follow the handbook-first
   workflow in [`docs/CONTRIBUTING.md`](CONTRIBUTING.md). Add or update tests.
2. **Run the checks locally** (fast feedback, see below).
3. **Open a PR to `main`.** GitHub Actions runs the same checks and gates the
   merge.

## Running the checks locally

Everything CI runs, you can run here first:

```bash
composer ci:check     # eslint + prettier (format) + tsc (types) + pint + pest
composer analyze      # phpstan / larastan
```

Individually:

| check | command |
|---|---|
| ESLint | `npm run lint:check` |
| Prettier | `npm run format:check` |
| TypeScript | `npm run types:check` |
| Pint (PHP style) | `composer lint:check` |
| PHPStan / Larastan | `composer analyze` |
| Pest + ≥70% coverage | `php artisan test --coverage --min=70` |

Auto-fix the formatting checks: `npm run lint` (eslint --fix), `npm run format`
(prettier --write), `composer lint` (pint).

### Tests

`php artisan test` runs the Pest suite against PostgreSQL. `phpunit.xml` points
the test connection at a dedicated `velsa_test` database, so create one on your
local Postgres before the first run:

```bash
createdb velsa_test
```

Coverage needs no extra flags (`memory_limit` is raised in `phpunit.xml`), so
`php artisan test --coverage --min=70` just works once a coverage driver (pcov
or Xdebug) is loaded. Some feature tests render Inertia pages, which need the
Vite manifest and Wayfinder modules: run `npm run build` once (it generates
both) if you see a `Vite manifest` error.

## CI

GitHub Actions ([`.github/workflows/ci.yml`](../.github/workflows/ci.yml)) runs
on every push to `main` and every PR. It spins up a PostgreSQL service, installs
dependencies, builds assets, and runs the full check set above (lint, format,
types, pint, phpstan, and the Pest suite with the coverage gate), plus a source
hygiene scan. A red check blocks the merge.

## Key files

| file | what it is |
|---|---|
| `VERSION` | the release version |
| `composer.json` / `package.json` | the `ci:check` / `lint:check` / `test` scripts |
| `phpunit.xml`, `pint.json`, `phpstan.neon`, `eslint.config.js` | check configs |
| `docs/CONTRIBUTING.md` | the handbook-first workflow for user-facing changes |
