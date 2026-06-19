---
title: Reports
section: Reports
order: 60
surfaces:
  - route: /reports
    method: GET
  - route: /reports/{slug}
    method: GET
  - component: pages/reports/show
tour_ids:
  - report-filters
  - report-apply
  - report-summary
  - report-table
  - report-export-csv
  - report-export-excel
  - report-export-pdf
  - report-schedule
---

The Reports section (`/reports`) lists every named report plus any
user-defined reports built via the ad-hoc report builder. Click any
report to open it; the report page has filters along the top and a
chart or table of results.

:::video reading-reports

## Reading a report

Every report opens on the same generic page (`/reports/{slug}`), so the
chrome is identical no matter which report you picked - only the data
differs:

- **Filters** - the bordered filter bar across the top accepts a date
  range, venue, and/or status as the report defines. Set them, then
  click **Apply** to re-run the report; the filters are reflected in
  the URL so a filtered view is shareable.
- **Summary** - a row of summary chips above the table surfaces the
  report's headline figures (totals, counts, attainment, balanced
  checks).
- **Table** - the detail table below lists every matching row.
- **Export** - the **Download CSV**, **Download Excel**, and **Download
  PDF** buttons at the top-right each apply the same filters as the
  on-screen view.
- **Schedule** - **Schedule this report** saves the current filters and
  emails the result on a cadence (needs the *schedule reports*
  permission).

The named set includes **sales goal attainment**, **revenue forecast
vs. actual**, and the **event schedule** report (whose **Spaces** column
lists the space(s) each booking occupies). They all share the chrome
above.

## What you can do

- **View** - every report renders as an HTML page with summary stats,
  optional chart, and a detail table
- **Filter** - most reports accept a date range, venue, and/or status
  filter. Filters compose and are reflected in the URL so a filtered
  view is shareable.
- **Export CSV** - every report has a download button at the top-right.
  Uses the same filters as the on-screen view; safe for very large
  date ranges (streamed, not buffered).
- **Export Excel** - alongside the CSV button. Native `.xlsx`
  workbook with a frozen header row, branded title block, the
  parameters that were applied, the summary chips, and the data
  table. Open in Excel / Numbers / Sheets and pivot or sort
  immediately - no import step.
- **Export PDF** - alongside the CSV button. Renders a print-ready
  landscape page with your branded header, the parameters that were
  applied, the summary chips, and the full row table. Opens in a new
  tab; save or print from there.

## Named reports (shipped today)

Sixteen reports ship out of the box. All live under `/reports/{slug}`:

| Report | Slug | What it shows |
| --- | --- | --- |
| Booked locations | `booked-locations` | Bookings by venue + space + status, date-range filterable |
| Location availability | `location-availability` | Per-space utilization vs. capacity over a window |
| Event bulletin | `event-bulletin` | One-day ops handout: every booking on the chosen date, time + venue + contact + attendance |
| Event schedule | `event-schedule` | Master chronological list of every booking in the window, venue-filterable |
| Calendar of events | `calendar-of-events` | Forward-looking calendar view of definite + completed events, weekday histogram |
| Event attendance | `event-attendance` | Estimate vs. actual attendance with variance and percentage |
| Event services schedule | `event-services-schedule` | Department-tagged outline items across all bookings, department-filterable |
| Sales pipeline | `sales-pipeline` | Open opportunities by stage with weighted value |
| Sales goal attainment | `sales-goal-attainment` | Per-salesperson revenue goal vs. actual booked revenue, variance + attainment % |
| Forecast vs. actual | `forecast-vs-actual` | Per-event forecast vs. actual for revenue (quoted vs. invoiced) and attendance (estimate vs. actual), with variance + window totals |
| AR aging | `ar-aging` | Outstanding invoices by aging bucket (current / 1-30 / 31-60 / 61-90 / 90+) |
| Clerk of Court - Monthly AR | `clerk-monthly-ar` | Monthly hand-off artifact: invoices issued, movement totals from journal entries on AR + bad-debt accounts, period-end balance + aging snapshot |
| Work-order status | `work-order-status` | Open / overdue / completed work orders by venue + department |
| Inventory utilization | `inventory-utilization` | Equipment-catalog draw-down by item over a window |
| F&B requirements | `food-and-beverage-requirements` | Aggregated F&B needs across upcoming events |
| Event status changes | `event-status-changes` | Audit of booking-status transitions in a window |
| Budget vs. actual | `budget-vs-actual` | Per-account variance against the fiscal-year budget |

The accounting journal at `/accounting` is a free-form query surface
over the journal, not a named report - see
[Accounting overview](/docs/accounting/overview).

## Ad-hoc report builder

For one-off reports outside the named set, the **Ad-hoc report
builder** at `/admin/report-builder` lets you compose a saved report
without code. See [Ad-hoc report builder](/docs/reports/ad-hoc-builder).

## Saved reports

Reports built via the builder save into the `report_definitions` table
and appear in the global registry alongside the named reports - they
show up on `/reports` automatically and respect the same filter
plumbing.

## CSV export specifics

- Filename includes the report slug + current date so exports don't
  collide
- First row is the header
- Streamed output - safe for arbitrarily large date ranges
- Currency columns render as decimal dollars (e.g. `1234.56`); date
  columns as ISO 8601

## Excel export specifics

- Same filename pattern as CSV but with a `.xlsx` extension
- Single-sheet workbook with a header strip (org name, title,
  description), generated-at + applied-parameter rows, summary chips
  in label/value form, then the data table
- Column header row is dark with white bold text, frozen so it stays
  visible while scrolling
- All data columns auto-size to their content
- Right-aligned columns (currency, counts) carry the alignment over
  from the on-screen view

## PDF export specifics

- Same filename pattern as CSV but with a `.pdf` extension
- Landscape orientation so wide reports fit without column truncation
- Shows the parameters used (date range, venue filter, etc.) in a
  header strip so the artifact is self-explanatory after it leaves
  the app
- Summary chips render as a top row; the detail table follows
- Footer carries the generation timestamp and the row count

## Scheduled delivery

Any report can be **emailed on a schedule**. Open the report, set the
filters you want, and use **Schedule this report** (needs the *schedule
reports* permission):

- **Frequency** - daily, weekly (pick a day), or monthly (pick a day of
  the month), at an hour you choose.
- **Format** - PDF, Excel, or CSV; the file is attached to the email.
- **Recipients** - one or more email addresses.

The report's **current filters are saved with the schedule** and
re-applied on every run, so a "Monthly AR aging for the Tourism fund"
delivery keeps reporting the same view. An hourly job sends the
schedules due that hour; each fires once per period. Manage or remove a
report's schedules from the same panel.

## What's not in reports yet

- **Result caching** - long-running reports re-run on every page
  load; no cache layer yet
