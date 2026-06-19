<x-mail::message>
# Refund issued

Hi {{ $recipientName }},

A refund has been posted against invoice `{{ $invoice->number }}`.

**Refund details**

- Refunded: **${{ number_format($amountCents / 100, 2) }}**
- Posted: {{ now()->format('M j, Y g:i A') }}
@if ($reason)
- Reason: {{ $reason }}
@endif

**Invoice summary**

- Invoice: `{{ $invoice->number }}`
- Original total: ${{ number_format($invoice->total_cents / 100, 2) }}
- Paid to date: ${{ number_format($invoice->paid_cents / 100, 2) }}
- Outstanding balance: **${{ number_format($invoice->balanceCents() / 100, 2) }}**

@if ($invoice->balanceCents() <= 0)
This invoice has no outstanding balance.
@else
There's still a balance on this invoice. Please reach out if you have
questions about next steps.
@endif

If you have any questions about this refund, just reply to this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
