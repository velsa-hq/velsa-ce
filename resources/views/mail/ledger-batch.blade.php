<x-mail::message>
# Journal export {{ $batch->period }}

A general-ledger export batch is ready and attached as a CSV.

**Batch details**

- Period: **{{ $batch->period }}**
- Entries: {{ $batch->entry_count }}
- Debits: ${{ number_format($batch->debit_total_cents / 100, 2) }}
- Credits: ${{ number_format($batch->credit_total_cents / 100, 2) }}
- In balance: {{ $batch->debit_total_cents === $batch->credit_total_cents ? 'yes' : 'NO - review before posting' }}

The attached file follows the configured export-template layout. Once
your GL system has accepted it, mark the batch acknowledged in the app.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
