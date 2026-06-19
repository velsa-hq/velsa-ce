---
title: Data import
section: Admin
order: 68
surfaces:
  - route: /admin/imports
    method: GET
  - route: /admin/imports
    method: POST
  - route: /admin/imports/{importJob}
    method: GET
  - route: /admin/imports/{importJob}/preview
    method: POST
  - route: /admin/imports/{importJob}/commit
    method: POST
  - route: /admin/imports/{importJob}/reverse
    method: POST
  - route: /admin/imports/{importJob}/errors
    method: GET
  - route: /admin/imports/{importJob}
    method: DELETE
  - component: admin/imports/index
  - component: admin/imports/show
tour_ids:
  - import-kind
  - import-upload
  - import-map
  - import-preview
  - import-commit
  - import-errors
  - import-reverse
---

**Data import** brings records from a spreadsheet (or an export from your
old system) into Velsa, one record type at a time. It's a generic,
**source-agnostic** framework: you upload a CSV, tell Velsa which of your
columns maps to which field, preview the result, and commit. Nothing is
written until you say so, and a committed import can be reversed for a
short window if it went wrong.

It lives at `/admin/imports` and is gated by the **Data import**
permission.

## What you can import

Each importable record type is a **kind**. The list grows over time;
available kinds are:

- **Clients** - the organizations and individuals you do business with
  (name, type, industry, source, notes, and a primary contact's email +
  phone).
- **Chart of accounts** - GL account codes (code, name, type, subtype,
  description, parent, postable). See the note on parents below.
- **Bookings** - events with their venue, client, dates, and an optional
  space placement. See the note on bookings below.

All kinds follow the same upload -> map -> preview -> commit flow described
here, so once you've imported one kind you know them all.

### Chart of accounts: parents

An account names its parent by the **parent's code** (not an internal
id). The parent can be an account already in the system or one defined
**earlier in the same file**, so the cleanest import is one **sorted by
code** (parents come before their children). A row whose parent appears
later - or doesn't exist anywhere - fails with a clear message; fix the
ordering or import the parent first, then re-import just the failures.
The account type accepts the obvious words (asset, liability, equity,
revenue/income, expense) and the **normal balance is derived from the
type**, so you don't have to supply it.

### Bookings: FK resolution + overlap

Each row is one booking and, optionally, **one space placement**. The
**venue** (required), **client**, and **space** are resolved **by name** -
the venue and client must already exist (import clients first), and the
space must exist inside that venue. Status accepts the obvious words and
**defaults to Definite** (suited to migrating historical events); dates
are parsed flexibly (e.g. `2024-06-01 09:00`). A row whose dates collide
with an existing **Definite/Completed** booking on the same space is
rejected - the importer won't double-book - so resolve the conflict and
re-import that row. Multi-space bookings aren't expressed in one row;
import the primary space and add the rest in-app.

## The flow

Importing is a four-step pipeline. A job carries its place in the
pipeline as a **status**, so you can leave and come back.

1. **Upload** - pick a kind, choose a `.csv` file, and say whether the
   first row is a header. Velsa stores the file and reads its columns.
2. **Map** - for each Velsa field, choose which of your CSV columns
   feeds it (or leave it blank). Velsa pre-guesses obvious matches by
   name. Required fields are marked; you can't preview until they're
   mapped.
3. **Preview** - Velsa runs every row through validation **without
   writing anything** (a dry run) and reports how many rows are valid,
   how many would fail, and why. Fix your file or your mapping and
   preview again as many times as you like.
4. **Commit** - Velsa imports the valid rows for real, each in its own
   savepoint so one bad row never poisons the rest. You get a final
   count of created rows and errors.

## Mapping columns

The mapping step is the heart of it. Each **target field** lists:

- its **label** and a short hint of what it expects,
- a **required** marker if a row can't be imported without it,
- a **source column** dropdown of your CSV's columns.

Velsa auto-maps any field whose name obviously matches one of your
columns (e.g. a `name` column -> the **Name** field); adjust anything it
got wrong. Enumerated fields (like a client's **type**) accept your
source values loosely - `Govt`, `government`, and `Government` all land
on the same type - and a value Velsa can't recognize becomes a row error
rather than a silent default.

## Preview (the dry run)

Preview validates the whole file and shows you, **before any write**:

- **rows total**, **rows valid**, **rows with errors**,
- a sample of the errors with the offending row number, field, and
  message.

A preview never changes data - run it as often as you need. The commit
button stays disabled until a preview has run and at least one row is
valid.

## Commit

Commit imports the valid rows. Each row runs in its own database
savepoint: a row that fails validation or hits a constraint is recorded
as an error and skipped, and the rest still import. When it finishes the
job shows **rows created** and **rows failed**, and the full error list
is downloadable as a CSV (row number, field, message, and the original
row) so you can fix and re-import just the failures.

## Read-only mode for risky imports

High-risk kinds - **bookings** and other bulk/financial imports - require
[read-only mode](/docs/admin/read-only-mode) to be on before they'll
commit, so the dataset can't shift mid-import. Commit is blocked with a
clear message until you enable it (Admin -> System settings). The job
records that read-only covered the run, which is what makes a clean
reversal possible. Low-risk kinds (clients, chart of accounts) don't
require it.

## Reversing an import

A committed import can be **reversed** within **7 days**. Reversing
deletes the records that import created - and only those - using a record
of exactly what it inserted. A record that can't be safely removed
(because something else now references it) is left in place and reported,
so a reversal never cascades into unrelated damage. After the window the
import locks and can no longer be reversed (the records remain as normal
data).

## What gets logged

Every import job and its outcome are written to the [audit
log](/docs/admin/audit-log): who ran it, the kind, the file name, and the
created/failed counts. The per-row error detail lives with the job and is
downloadable as described above.

## Why generic (vs. a Momentus-specific importer)

The framework hard-codes nothing about any one source system. Migrating
off an incumbent is then just a **saved column map** for that system's
export - the same upload -> map -> preview -> commit path everyone else
uses, with no special-case code to maintain. It also means the day-to-day
"finance handed me a spreadsheet of new clients" job uses the exact same
tool as a one-time migration.
