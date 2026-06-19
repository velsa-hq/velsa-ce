# Development workflow

This document captures how we add features to Velsa.

The pattern is **handbook-first** for user-facing surfaces: write the
user-manual prose describing the intended behavior before writing
code, build the feature, then verify the prose still matches reality.
Automated tests and the handbook prose are the two layers of coverage.

## When the pattern applies

| Change kind | Handbook page required? |
| --- | --- |
| New user-facing page, panel, or workflow (admin or end-user) | **Yes** |
| New named report, new dashboard surface, new email template | **Yes** |
| Renaming or restructuring an existing user-facing surface | **Yes** (update the existing page) |
| Behavior change visible to the user (status flow, validation rule, copy) | **Yes** (update the existing page) |
| Pure refactor with no user-visible change | No |
| Bug fix that doesn't change the documented behavior | No |
| Backend service / API / observer with no UI | No |
| Infrastructure (Docker, CI, deployment, dependency bumps) | No |

When in doubt, ask: *if a sales rep, an ops lead, or an evaluator read
the handbook for this feature, would they expect this change to be
documented?* If yes, write the page.

## The flow

1. **Draft the handbook page** in `docs/handbook/<section>/<slug>.md`
   using the existing prose style (see any page in `docs/handbook/`
   for tone). Front-matter shape:

   ```yaml
   ---
   title: Issue an installment invoice
   section: Contracts
   order: 4
   surfaces:
     - route: /bookings/{booking}/payment-schedule
       method: PUT
     - component: bookings/show#PaymentSchedulePanel
   tour_ids:
     - bk-payment-schedule-add
   ---
   ```

   - `surfaces` - the route(s) and/or Inertia page anchors the page
     documents. Future drift checks read these.
   - `tour_ids` - the `data-tour-id` attributes the page references in
     screenshots or "click the X button" instructions.

2. **Paste the page into the PR description** when you open the PR.
   Reviewers see the user-facing contract first, the code diff
   second. The PR template (`.github/pull_request_template.md`) has a
   slot for this.

3. **Build the feature** in the usual way - controllers, models,
   migrations, React pages, tests. Wire up the `data-tour-id`
   attributes on the elements the handbook page calls out.

4. **Run tests + dev verification.** Pint + larastan + Pest + the
   page in a browser. Standard.

5. **Reconcile the handbook before merging.** Re-read the draft page
   against the shipped behavior. If they disagree:
   - Decide which is right (often the implementation revealed a
     better model than the draft).
   - Edit the loser. **Never let them silently diverge.**

## Drift containment

A handbook page and its implementation may only disagree across a
single PR - never on `main`. The reconcile step (step 5) is the
mechanism.

Future tooling will enforce this automatically: a CI check that
verifies every `surfaces:` route in handbook front-matter resolves to
a real route in `php artisan route:list`, and every `tour_ids:`
entry appears in at least one rendered React component. Until that
ships, the reviewer carries the check by reading the handbook page
alongside the diff.

## Small commits, squash-merge

We land features as **small focused commits on a feature branch**,
opened as a PR, squash-merged to `main`. The branch can be as noisy as
you want; `main` reads as one clean commit per shipped slice.
