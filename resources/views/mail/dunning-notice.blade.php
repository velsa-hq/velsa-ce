<x-mail::message>
@switch($stage->value)
    @case('first_notice')
# Friendly reminder

Hi {{ $recipientName }},

Just a heads-up that invoice **{{ $invoice->number }}** is now
**{{ $daysPastDue }} day(s) past due**. The balance remaining is
**${{ number_format($invoice->balanceCents() / 100, 2) }}**.

If you've already sent payment, please disregard. Otherwise, you can
pay your balance through the exhibitor portal at your convenience.
        @break

    @case('second_notice')
# Past due - please remit

Hi {{ $recipientName }},

Invoice **{{ $invoice->number }}** is now **{{ $daysPastDue }} days past
due**. The outstanding balance is
**${{ number_format($invoice->balanceCents() / 100, 2) }}**.

Please remit payment promptly to keep your account in good standing.
        @break

    @case('final_notice')
# Final notice

This is a final notice that invoice **{{ $invoice->number }}** is now
**{{ $daysPastDue }} days past due** with an outstanding balance of
**${{ number_format($invoice->balanceCents() / 100, 2) }}**.

If payment is not received within the next 15 days, this account
will be referred to collections.
        @break

    @case('collections')
# URGENT - collections referral pending

Invoice **{{ $invoice->number }}**, originally due
{{ $invoice->due_on?->format('M j, Y') }}, remains unpaid
**{{ $daysPastDue }} days past due** with a balance of
**${{ number_format($invoice->balanceCents() / 100, 2) }}**.

This account has been flagged for referral to our collections process.
Please contact your event coordinator immediately to resolve.
        @break

    @default
# Invoice reminder

Invoice **{{ $invoice->number }}** has an outstanding balance of
**${{ number_format($invoice->balanceCents() / 100, 2) }}**.
@endswitch

**Invoice details**

- Number: `{{ $invoice->number }}`
- Issued: {{ $invoice->issued_on?->format('M j, Y') }}
- Due: {{ $invoice->due_on?->format('M j, Y') }}
- Total: ${{ number_format($invoice->total_cents / 100, 2) }}
- Paid: ${{ number_format($invoice->paid_cents / 100, 2) }}
- **Balance due: ${{ number_format($invoice->balanceCents() / 100, 2) }}**

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
