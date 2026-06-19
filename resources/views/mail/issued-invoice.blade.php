<x-mail::message>
# Invoice {{ $invoice->number }} is ready

Hi {{ $recipientName }},

@if ($eventName)
A new invoice has been issued for **{{ $eventName }}**.
@else
A new invoice has been issued.
@endif

**Invoice details**

- Number: `{{ $invoice->number }}`
- Issued: {{ $invoice->issued_on?->format('M j, Y') }}
- Due: {{ $invoice->due_on?->format('M j, Y') }}
- Amount due: **${{ number_format($invoice->total_cents / 100, 2) }}**

You can pay the balance through your event coordinator. Reach out if
the details look off or you need a different payment cadence.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
