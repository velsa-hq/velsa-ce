---
title: Contracts overview
section: Contracts
order: 30
surfaces:
  - route: /contracts
    method: GET
  - route: /contracts/{contract}
    method: GET
  - component: contracts/index
  - component: contracts/show
---

A **contract** is the legal document a client signs to commit to a
booking. Contracts are drafted from a booking, sent to one or more
signers via DocuSign, and tracked through their lifecycle.

## Status lifecycle

| Status | Meaning |
| --- | --- |
| Draft | Created but not sent. Can be **deleted** (moved to the archive, restorable) or re-drafted from the booking. In-place body editing isn't available yet - adjust the template and re-draft. |
| Sent | Sent to signers via DocuSign; awaiting first view. Can be **voided** from the contract show page. |
| Viewed | At least one signer has opened the envelope |
| Partially signed | Some signers signed but not all |
| Signed | All signers signed. The contract body becomes immutable; future changes must be a separate Addendum. |
| Declined | A signer rejected the envelope |
| Expired | A sent/viewed envelope that passed the configured expiry window (Admin -> System settings -> Defaults -> "Contract expiry window"). Marked automatically by a nightly job. |
| Voided | The envelope was voided after sending - by us via the contract show page, or by DocuSign returning an `envelope-voided` Connect event |

After a contract reaches **Signed** or **Partially signed**, any
further amendments must take the form of an addendum - a child
contract with `kind = 'addendum'` that carries its own envelope and
its own lifecycle. See [Addenda](/docs/contracts/addenda) for the
workflow.

## Kind

Every contract carries a `kind` that drives the UI's framing:

- **`contract`** - the standard event contract drafted from a booking
- **`addendum`** - a child of an existing Signed / Partially signed
  contract, drafted from the parent's show page

Both kinds share the same status enum, the same DocuSign pipeline,
and the same `AD-...` / `CT-...` reference-format generators. The
contracts index displays both kinds with a small badge so addenda
are visually distinct from primary contracts.

## The detail page

Every contract reference in the system is a link to its detail page
at `/contracts/{id}`. You'll get there from the contracts index,
the booking detail page's Contracts card, or the client detail
page's Contracts list - all of them link to the same view.

The page is laid out as a wide left + narrow right:

- **Header** - reference, status chip, kind, total, a **Send**
  button (for drafts) or a **Create addendum** button (for Signed /
  Partially signed parents), and the parent booking + client (both
  linked)
- **Signers** - ordered list with name, role, email, and per-signer
  state (awaiting / viewed / signed / declined) with timestamps
- **Rendered contract** - the fully-rendered HTML body in a
  scrollable card (or a note that no template was attached)
- **Lifecycle** - vertical timeline of created -> sent -> viewed ->
  signed (or declined / expired / voided) with timestamps
- **Provider** - DocuSign envelope ID, provider name, creator
- **Related** - parent contract link (if this is an addendum) and
  the addenda hanging off this contract (if there are any)

## DocuSign

Two providers ship with the app, swapped by the
`integrations.docusign.enabled` system setting:

- **Fake provider** (default) - every send returns a placeholder
  envelope ID and the status transitions happen on a timer, so the
  contract lifecycle can be walked end-to-end without a real
  DocuSign account. Useful for demos, training-video recording,
  and CI.
- **DocuSign provider** - real JWT-grant authentication against
  the configured DocuSign account; envelopes are created and routed
  for real signature. Connect webhooks at `/webhooks/docusign`
  drive status transitions back into the system.

The contracts index shows which mode is currently active in the
header so there's no ambiguity. The swap is reversible at any time
- flipping the setting back to fake doesn't void in-flight
envelopes; it just routes new sends through the fake path.

### Verifying a live DocuSign connection

The real provider needs JWT-grant credentials that the test suite
deliberately never exercises, so bringing up a real (developer or
production) account is a one-time, hands-on step. The
`docusign:doctor` artisan command is the go-live check:

- `php artisan docusign:doctor` - audits the configured settings
  (masked) and attempts a real JWT token grant. On first setup
  DocuSign returns `consent_required`; the command prints the
  one-time admin consent URL to open in a browser. The consent
  `redirect_uri` must be registered on the integration key.
- `php artisan docusign:doctor --send=you@example.com` - sends a
  real envelope (latest draft, or `--contract=ID`) so a human can
  sign it from email.
- `php artisan docusign:doctor --status=<envelopeId>` - fetches the
  live envelope status.

After signing the test envelope, `contracts:reconcile-signatures`
flips the contract to Signed and stores the executed PDF - the same
path the Connect webhook drives, so the full surface is exercised
without a publicly reachable webhook. Note the OAuth host is pinned
from the configured `oauth_base`; a developer (demo) account uses
`account-d.docusign.com`, production uses `account.docusign.com`.

Deleted contracts move to the **archive** (`/contracts/archive`),
searchable and restorable; signed and in-flight contracts can't be
deleted (void an in-flight one first).

Once a contract is fully signed, the **executed PDF** (all documents +
the completion certificate) is captured from the provider and offered
as **Download signed PDF** on the contract page - the contract of
record. Inbound Connect webhooks are **HMAC-verified** when the Connect
key is configured (see [System settings -> DocuSign](/docs/admin/system-settings)),
and an hourly reconciliation sweep catches any missed webhook.

See [Drafting and sending](/docs/contracts/drafting-and-sending)
for the day-to-day workflow and [System settings -> DocuSign](/docs/admin/system-settings)
for the provider-configuration page.

## Templates

Contracts, addenda, proposals, invoices, and payment schedules are
all rendered from reusable templates. A template is a piece of HTML
with placeholders like `{{booking.name}}` and `{{client.name}}`. A
standard event-contract template ships out of the box; new
templates can be added per venue or as a global default. See
[Document templates](/docs/admin/document-templates) for the admin
surface that manages them.

Template placeholders the renderer fills in:

- `{{booking.reference}}`, `{{booking.name}}`,
  `{{booking.start_date}}`, `{{booking.end_date}}`,
  `{{booking.total}}`
- `{{venue.name}}`
- `{{client.name}}`

The renderer uses `data_get` semantics, so any nested path that
exists in the vars passed at draft time will resolve - these are
just the conventional ones.

## See also

- [Drafting and sending](/docs/contracts/drafting-and-sending) -
  end-to-end send workflow
- [Addenda](/docs/contracts/addenda) - immutability rule + addendum
  drafting
- [Document templates](/docs/admin/document-templates) - admin
  CRUD for the underlying HTML templates

## Exporting

Alongside the signed-PDF download, every contract has a **Download as Word**
action that produces an editable Microsoft Word document of the contract - useful
when a client or counsel needs to redline terms outside the system. (The
authoritative signed copy remains the DocuSign-completed PDF.)
