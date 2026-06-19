---
title: Exporting a contract to Word
section: Contracts
order: 33
surfaces:
  - route: /contracts/{contract}/document.doc
    method: GET
  - component: contracts/show
tour_ids:
  - ct-download-word
---

**Download as Word** exports the rendered contract as a `.doc` file
(named after the contract reference, e.g. `CT-10042.doc`) so it can be
opened and edited offline in Microsoft Word or any compatible editor.

:::video export-contract-as-word

## When to use it

The Word export exists for one job: getting the contract body out of the
app and into a word processor where counsel can redline it. Use it when
a lawyer or the other party wants to mark up language **outside** the
signing flow - track-changes, margin notes, alternate clauses.

The export is a snapshot of the contract as currently rendered. Editing
the downloaded file changes only that local copy; it does not write back
to the contract in Velsa.

## What it is not

The Word file is **not** the authoritative document. The signed copy of
record is always the DocuSign PDF, retrieved with **Download signed PDF**
once signing completes. Treat the `.doc` as a working draft for
negotiation only - once language is agreed, fold the changes back into
the contract template (or a fresh draft) and send that for signature so
the PDF on file matches what everyone signed.

## Word export vs. the signed PDF

| | Word export (`.doc`) | Signed PDF |
| --- | --- | --- |
| Purpose | Offline redlines, negotiation | Authoritative signed record |
| Editable | Yes, locally | No |
| Source of truth | No | Yes |
| Available | Any contract | After DocuSign signing completes |
