---
title: Document templates
section: Admin
order: 77
surfaces:
  - route: /admin/document-templates
    method: GET
  - route: /admin/document-templates/create
    method: GET
  - route: /admin/document-templates/{documentTemplate}
    method: GET
  - component: admin/document-templates/index
  - component: admin/document-templates/{create,edit}
tour_ids:
  - admin-dt-new
  - admin-dt-kind-select
  - admin-dt-scope-select
  - admin-dt-body-editor
  - admin-dt-preview-toggle
  - admin-dt-save
---

**Document templates** are the HTML bodies that the system renders
into proposals, contracts, addenda, invoices, and payment-schedule
documents. When a sales rep clicks **Draft contract** on a booking,
the system picks the most-specific active template of the requested
kind (venue-scoped beats global) and substitutes merge fields like
`{{booking.name}}` and `{{venue.name}}` against the booking's data.

Lives at `/admin/document-templates`.

## Page layout

The index groups templates by kind:

- **Proposal**
- **Contract**
- **Addendum**
- **Invoice**
- **Payment schedule**

Each row shows the template name, scope (Global vs the specific
venue), version, an Active/Inactive badge, the count of contracts
that have used it, and the last-updated date. Click a name to open
the editor (where the **Active** state is actually toggled);
**Delete** on a row removes it (with a confirmation that surfaces
the contracts-used count so you know what you're severing).

A kind with no template yet displays a dashed empty-state with a
**Create one** link that pre-selects the kind in the create form.

## Scope: global vs venue-specific

- **Global** (`venue_id = NULL`) - applies to every venue. The
  default starter set lives here.
- **Venue-specific** (`venue_id` set) - only surfaces when drafting
  a document for a booking at that venue.

The draft flow resolves with `forVenueKind($venueId, $kind)`: venue-
specific match wins; otherwise the global template of the same kind
wins; otherwise no template (the contract is created with a `NULL`
template_id and an empty rendered body, which you can fill in
manually on the contract show page).

## Editor

The edit page has two columns:

**Left column** - the form:

- **Kind** - Proposal / Contract / Addendum / Invoice / Payment
  schedule
- **Scope** - Global, or pick a venue from the dropdown
- **Name** - what shows in the index list
- **Active** checkbox - only Active templates are eligible for the
  draft flow's most-specific-match resolution
- **Body (HTML)** - a large monospace `<textarea>` for the HTML
  source. The **Preview** button swaps the editor for a rendered
  view where merge tokens like `{{venue.name}}` become amber
  highlights so you can eyeball merge-field placement before
  saving. The same button reads **Show source** in preview mode -
  click it to flip back to the textarea.

**Right column** - the merge-field reference:

A short list of the conventional tokens passed in by
`ContractDispatcher::renderForBookingTemplate`:

- `{{booking.reference}}` - `BK-2026-XXXX`
- `{{booking.name}}` - Event name
- `{{booking.start_date}}` - Formatted start
- `{{booking.end_date}}` - Formatted end
- `{{booking.total}}` - Formatted dollar amount
- `{{spaces}}` - Booked spaces/rooms, comma-joined (e.g. `Grand Ballroom, Room 201`)
- `{{venue.name}}` - Venue name
- `{{client.name}}` - Client name

The renderer uses `data_get` so any nested path that exists in the
vars passed at draft time will work - these are just the
conventional ones.

## Version bump

Editing the **body** auto-bumps the template's version. Renames and
scope changes don't bump. The version number is informational -
contracts drafted from version 3 keep their `rendered_html`
snapshot, so future contracts drafted from version 4 don't
retroactively rewrite anyone's signed copy.

## Signing fields (DocuSign anchors)

When a contract is sent through the real DocuSign provider, signing
fields are placed by **anchor text** - DocuSign searches the
rendered document for specific literal strings and drops the
matching field next to each. Templates just print the labels; no
coordinate math.

The primary signer's fields anchor to these strings (include them
in the template's signature block):

| Field | Anchor string in the body |
| --- | --- |
| Signature | `Signature:` |
| Initials | `Initials:` |
| Date signed (auto-filled) | `Date:` |
| "I agree" checkbox (required) | `agree to the terms` |

The seeded **Standard County Event Contract** template ships with an
Acceptance block containing all four. If you author a new contract
template and want fields placed automatically, reproduce those
labels; omit a label and that field simply isn't placed (no error -
the signer can still add one in DocuSign).

Anchors apply to the **primary** (first) signer. Additional signers
on a multi-signer envelope don't get an auto-placed block from the
single signature block - they sign without a pre-placed field. A
per-signer block convention is a future enhancement.

> On the fake/demo provider these anchors are ignored - fields only
> matter once `integrations.docusign.enabled` is on. See
> [Contracts overview -> Verifying a live DocuSign connection](/docs/contracts/overview).

## Delete safety

`Contract.template_id` is nullable with `nullOnDelete`. Deleting a
template that's already been used:

- Sets `template_id = NULL` on the historical contracts that used
  it.
- Leaves their `rendered_html` snapshot completely intact (that's
  what the client signed, after all).
- So past contracts keep displaying their captured body even after
  the template is gone.

The delete confirmation surfaces the contracts-used count so admins
know the scope before clicking through.

## See also

- [Contracts overview](/docs/contracts/overview) - the contract
  lifecycle the templates feed
- [Drafting and sending](/docs/contracts/drafting-and-sending) - how
  a chosen template renders against booking data at draft time
- [Addenda](/docs/contracts/addenda) - addendum templates use this
  same surface, just with `kind = addendum`
