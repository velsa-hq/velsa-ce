---
title: Drafting & sending
section: Contracts
order: 31
surfaces:
  - route: /bookings/{booking}/contracts/draft
    method: POST
  - route: /contracts/{contract}/send
    method: POST
  - route: /webhooks/docusign
    method: POST
  - component: bookings/show
  - component: contracts/show
tour_ids:
  - bk-draft-contract
  - ct-send
  - ct-send-dialog
  - ct-send-signer-email
  - ct-send-add-signer
  - ct-send-submit
  - ct-download-signed
---

Contracts are always drafted **from a booking** - there's no
standalone "new contract" form. Open the booking detail page, find
the Contracts card, and click **+ Draft contract**.

:::video draft-contract

## Drafting

Clicking + Draft contract immediately:

1. Looks up the most-specific active contract template for that
   venue, falling back to the global default. See
   [Document templates](/docs/admin/document-templates) for how
   resolution works.
2. Renders the template against the booking's data (reference,
   dates, client name, venue name, total) - placeholders like
   `{{booking.name}}` become live values, and the rendered HTML is
   snapshot onto the contract row at draft time.
3. Creates a Contract row with status **Draft** and a freshly-
   generated `CT-YYYY-XXXXX` reference.
4. Returns you to the booking detail page with a toast showing the
   new contract reference.

The new draft appears at the top of the Contracts card immediately.

A booking can have multiple drafts - useful if the first one had
the wrong dates or you want to compare two versions. Drafts don't
lock anything until they're sent.

The button is **hidden when the booking is cancelled** - you can't
draft against a cancelled booking. Re-activate the booking first if
you need to.

If no template of `kind = contract` exists for the venue (and no
global one either), the contract is still created - with
`template_id = NULL` and an empty `rendered_html`. There's no
in-place body editor yet: create or activate a matching
[Document Template](/docs/admin/document-templates), then re-draft so
the body renders from it.

## Sending

Drafts can be sent from two places:

- **The booking detail page** - each draft contract in the
  Contracts card has a Send button to its right
- **The Contracts index** (`/contracts`) - draft-status rows have
  a Send button in the Actions column

Either button opens the **Send for signature** dialog:

1. The dialog lists one or more signers, each with a name, email,
   and role. The first row is pre-filled with the client's name
   from the booking - add more rows (a venue representative, a
   second guarantor, etc.) with **Add signer**, or remove any
   extra row.
2. Signer order is the row order: the first row signs first, the
   second next, and so on. This becomes each signer's
   `signing_order`, which DocuSign honours as the routing order so
   later signers are only emailed once earlier ones have signed.
3. Submitting POSTs the full signer list to the
   `ContractDispatcher`, which creates the envelope via the bound
   `SignatureProvider` - fake or real DocuSign, depending on the
   `integrations.docusign.enabled` system setting (see
   [Contracts overview](/docs/contracts/overview))
4. The contract row gets its `provider_envelope_id`, status flips
   to **Sent**, the audit log records `contract.sent`, and the
   booking's narrative gets a "Contract REF sent for signature"
   auto-entry

A toast confirms the send. If the provider rejects the envelope
(bad credentials, a DocuSign outage), the dialog stays open and
surfaces the error instead of silently failing.

## Status transitions you'll see

Two pathways feed status changes back into the system:

**Fake provider** (default):

The fake `SignatureProvider` walks the envelope through view -> sign
on a timer so the contract lifecycle can be exercised end-to-end
without a real DocuSign account. The contract show page polls and
the status updates from Draft -> Sent -> Viewed -> Signed over the
course of a few seconds. Useful for demos and training-video
recording.

**Real DocuSign provider**:

When `integrations.docusign.enabled` is true, sends create a real
envelope via JWT authentication. Status transitions arrive via
DocuSign Connect webhooks posted to `/webhooks/docusign`:

| Connect event | Effect |
| --- | --- |
| `recipient-sent` | No-op (contract is already in Sent state from createEnvelope) |
| `recipient-delivered`, `recipient-viewed` | Marks the signer viewed |
| `recipient-completed` | Marks the signer signed; if last signer, contract -> Signed |
| `recipient-declined` | Contract -> Declined with the decline reason |
| `envelope-completed` | Force contract -> Signed without per-recipient context |
| `envelope-declined` | Contract -> Declined |
| `envelope-voided` | Contract -> Voided |
| Unknown envelope ID | Logged + 200 OK so DocuSign doesn't retry |

The webhook binds on `provider_envelope_id`, so events for an
addendum's envelope route only to that addendum - the parent
contract is untouched. (Verified by Pest test in
`DocuSignIntegrationTest`.)

## Audit + narrative

Every send writes a `contract.sent` row to the audit log and
appends a System-kind entry to the booking's Event narrative. Every
DocuSign webhook hit writes a `contract.docusign_webhook` audit row
with the event type, envelope ID, and the post-update status - so
if a customer disputes a timeline later, the webhook ledger is the
record of truth.

## See also

- [Contracts overview](/docs/contracts/overview) - status enum +
  the fake-vs-real provider swap
- [Addenda](/docs/contracts/addenda) - after a contract is signed,
  amendments become addenda
- [System settings -> DocuSign](/docs/admin/system-settings) - provider
  configuration (admin only)
