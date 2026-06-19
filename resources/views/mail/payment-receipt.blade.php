<x-mail::message>
# Payment received

Hi {{ $recipientName }},

Thank you - we've received your payment.

**Payment details**

- Amount: **${{ number_format($payment->amount_cents / 100, 2) }}**
- Method: {{ $payment->card_brand ?? 'card' }} {{ $payment->last4 ? '••'.$payment->last4 : '' }}
- Transaction: `{{ $payment->provider_transaction_id ?? '-' }}`
- Captured: {{ $payment->processed_at?->format('M j, Y g:i A') }}

**Order summary**

- Order: `{{ $order->order_number }}`
@if ($invoice)
- Invoice: `{{ $invoice->number }}`
@endif
- Total: ${{ number_format($order->total_cents / 100, 2) }}
- Paid to date: ${{ number_format($order->paid_cents / 100, 2) }}
- Balance remaining: **${{ number_format(max(0, $order->total_cents - $order->paid_cents) / 100, 2) }}**

@if (max(0, $order->total_cents - $order->paid_cents) === 0)
Your order is paid in full. We look forward to seeing you at the event.
@else
There's still a balance remaining on this order. You can pay the
remainder anytime through the exhibitor portal.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
