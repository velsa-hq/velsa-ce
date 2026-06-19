---
title: Pipeline stages
section: Admin
order: 76
surfaces:
  - /admin/pipeline-stages (pages/admin/pipeline-stages/index)
---

`/admin/pipeline-stages` tunes the six sales-funnel stages to match
how your team actually sells - without touching code.

:::video pipeline-tuning

## What you can change

| Field | Applies to | Notes |
| --- | --- | --- |
| **Label** | Every stage | The display name shown on the pipeline board, opportunity cards, and the edit form. Rename "Proposal sent" to "Quote out," etc. |
| **Probability** | Open stages only | The default win-probability a stage stamps on opportunities. Feeds the weighted pipeline forecast. |

Won and Lost are **fixed** at 100% and 0% - a closed deal's weight
isn't a forecasting knob - so only their labels are editable.

## How probability is applied

The stage probability is a **default**, stamped on an opportunity when
it's created and re-applied when you drag it to a new stage on the
board. It is **not** retroactive: editing a stage's probability here
leaves existing opportunities alone (they keep whatever value they
carry), and an individual opportunity's probability can always be
fine-tuned on its [edit form](/docs/pipeline). New movement after you
save picks up the new default.

## Reverting

Clear a label back to its built-in name by re-typing the original, or
leave a probability at its shipped default. There's no separate
"reset" - saving simply stores whatever's in the form, and any field
left at the built-in value is treated as no override.

Changes take effect on the next page load; reads are cached and the
cache clears automatically on save.
