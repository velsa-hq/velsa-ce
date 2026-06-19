---
title: Contract addenda
section: Contracts
order: 32
surfaces:
  - route: /contracts/{contract}/addenda
    method: POST
  - component: contracts/show#CreateAddendumButton
tour_ids:
  - ct-create-addendum
  - ct-addendum-reason
  - ct-addendum-submit
---

A **contract is immutable once signed.** Any change to scope, date,
or fee against a signed contract lands as a separate **Addendum**
that references the original. The parent contract stays
byte-for-byte as the client signed it; the addendum carries the
delta and gets its own signature workflow.

The governing acceptance criterion: "A signed Contract is
immutable - any change creates a new Addendum, never modifies the
original."

## When to draft an addendum

The **Create addendum** button surfaces on the contract show page
only when the parent contract is `Signed` or `Partially signed`.
Draft, Sent, Viewed, Declined, Expired, and Voided contracts
display nothing - those need their problem fixed before an
addendum makes sense.

Common reasons:

- Date shift after signature
- Scope change (added a space, added an additional event day)
- Fee adjustment (negotiated a price change, applied a one-off
  credit)
- Replaced a damaged item or accommodated a change request

## Drafting flow

1. Open the parent contract at `/contracts/{id}`.
2. Click **Create addendum** in the header. An inline form drops
   in below the title.
3. Type a short **reason** (e.g. "Date shift to October 15"). The
   reason isn't enforced - it's preserved as a header on the
   draft addendum body so the recipient sees what changed.
4. Click **Draft addendum**. The system creates a child contract
   with kind `addendum`, status `Draft`, an `AD-...` reference, and
   redirects you to the new addendum's show page.
5. From there the addendum follows the same send / sign / decline
   lifecycle as any other contract - see
   [Drafting and sending](/docs/contracts/drafting-and-sending).

## Reference format

Addenda use the `AD-YYYY-XXXXX` reference prefix (vs `CT-...` for
contracts), so the list view at `/contracts` makes it obvious which
row is which without opening it. The reference is generated on
create and immutable.

## Immutability propagates

The same signed-state immutability rule applies to addenda. A
signed addendum can't have its body, total, or template edited -
you'd draft an addendum on the addendum, and so on. In practice
most events take 0-1 addenda; a string of nested addenda is a
signal that something's wrong with the original contract.

## Related card

The parent contract show page has a **Related** card on the right
that lists every addendum drafted against it (with status). The
addendum's show page has the inverse - a **Parent** link back to
the original. Use either to walk the chain.

## See also

- [Contracts overview](/docs/contracts/overview) - status
  lifecycle, where contracts live
- [Drafting and sending](/docs/contracts/drafting-and-sending) -
  the standard draft -> send -> sign flow that addenda also use
