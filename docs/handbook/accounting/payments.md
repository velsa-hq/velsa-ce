---
title: Recording payments
section: Accounting
order: 47
---

Two payment paths exist depending on whether the customer is paying
through the BluePay rail (online card) or outside it (check, wire,
cash, ACH).

## Online card payments (BluePay)

For exhibitor orders, the customer pays from the exhibitor portal
(`/portal/orders/{id}/pay`). The flow:

1. Exhibitor sees the order balance, clicks **Pay now**
2. A BluePay hosted iframe tokenizes the card (PCI scope stays off
   your infrastructure)
3. The system submits the token to the processor with an idempotency
   key
4. On approval: a payment record is created, the order is credited,
   the journal entries post (debit Cash, credit AR), and the receipt
   email is sent
5. On decline: the failure reason is stored, an audit entry is
   written, and the exhibitor sees an error on the pay page

Until merchant credentials are configured, the BluePay path runs in a
preview mode that always approves valid-looking tokens - useful for
demos and acceptance testing. Production behavior takes over once
credentials are in place.

## Manual payments (check / wire / cash / ACH)

From `/admin/invoices/{number}` -> **Record manual payment** card:

1. Method dropdown (check, wire, cash, ACH)
2. Amount (defaults to invoice balance)
3. Reference (e.g. "Check #4502", optional)
4. Note (free text, optional)

For **exhibitor-order invoices** the manual payment is recorded as a
distinct payment entry alongside any card captures, so the order
shows the full tender history. For **booking and client invoices**
the payment is applied directly to the invoice's running paid amount;
the same journal pair (debit Cash 1010, credit AR 1100) posts in
every case.

## Receipts

Every captured payment fires a receipt email to the paying party with
the invoice number, amount, method, and a PDF attachment of the
invoice. This applies to both card and manual payments.

## Idempotency

Charges use a deterministic idempotency key so a network retry can't
double-charge a card. On a second submit with the same key the
processor returns the prior result instead of capturing again.
