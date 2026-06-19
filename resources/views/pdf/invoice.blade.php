<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 1.25cm 1cm; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 11pt;
            line-height: 1.45;
            margin: 0;
        }

        header.brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        header.brand .org-name { font-size: 18pt; font-weight: 700; }
        header.brand .org-sub { font-size: 10pt; color: #6b7280; margin-top: 2px; }
        header.brand .invoice-meta { text-align: right; }
        header.brand .invoice-meta .label {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600;
        }
        header.brand .invoice-meta .number { font-size: 16pt; font-weight: 700; }
        header.brand .invoice-meta .status {
            display: inline-block; padding: 2px 8px; border-radius: 4px;
            font-size: 9pt; font-weight: 600; margin-top: 4px;
            background: #f3f4f6; color: #374151;
        }
        header.brand .invoice-meta .status.paid { background: #d1fae5; color: #065f46; }
        header.brand .invoice-meta .status.past_due { background: #fee2e2; color: #991b1b; }
        header.brand .invoice-meta .status.partial_paid { background: #fef3c7; color: #92400e; }
        header.brand .invoice-meta .status.void { background: #e5e7eb; color: #4b5563; }

        section.parties {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 22px;
        }
        section.parties .party { width: 48%; }
        section.parties .label {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; margin-bottom: 4px;
        }
        section.parties .name { font-weight: 600; }

        section.meta {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 22px;
        }
        section.meta .cell { flex: 1; }
        section.meta .label {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; display: block; margin-bottom: 2px;
        }

        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        table.lines thead th {
            background: #1f2937;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 9pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        table.lines thead th.amount { text-align: right; }
        table.lines tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.lines tbody td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        table.lines tbody td.muted { color: #6b7280; }

        table.totals {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 6px 10px;
            font-variant-numeric: tabular-nums;
        }
        table.totals td.label { text-align: right; color: #6b7280; }
        table.totals td.value { text-align: right; }
        table.totals tr.grand td { font-weight: 700; font-size: 12pt; border-top: 2px solid #1f2937; padding-top: 8px; }
        table.totals tr.balance td { color: #991b1b; font-weight: 600; }

        footer.legal {
            margin-top: 32px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            color: #6b7280;
            font-size: 9pt;
        }
    </style>
</head>
<body>

<header class="brand">
    <div>
        <div class="org-name">{{ $appName }}</div>
        <div class="org-sub">{{ $appSubtitle }}</div>
    </div>
    <div class="invoice-meta">
        <div class="label">Invoice</div>
        <div class="number">{{ $invoice->number }}</div>
        <div class="status {{ $invoice->status?->value }}">{{ $invoice->status?->label() }}</div>
    </div>
</header>

<section class="parties">
    <div class="party">
        <div class="label">From</div>
        <div class="name">{{ $appName }}</div>
        @if ($appSubtitle)
            <div>{{ $appSubtitle }}</div>
        @endif
        @if ($fromEmail)
            <div>{{ $fromEmail }}</div>
        @endif
    </div>
    <div class="party">
        <div class="label">Bill to</div>
        <div class="name">{{ $billToName ?: '-' }}</div>
        @if ($billToContact)
            <div>{{ $billToContact }}</div>
        @endif
        @if ($billToEmail)
            <div>{{ $billToEmail }}</div>
        @endif
    </div>
</section>

<section class="meta">
    <div class="cell">
        <span class="label">Issued</span>
        <span>{{ $invoice->issued_on?->format('M j, Y') ?: '-' }}</span>
    </div>
    <div class="cell">
        <span class="label">Due</span>
        <span>{{ $invoice->due_on?->format('M j, Y') ?: '-' }}</span>
    </div>
    <div class="cell">
        <span class="label">Source</span>
        <span>{{ $sourceLabel }}</span>
    </div>
    @if ($invoice->customer_reference)
        <div class="cell">
            <span class="label">Your reference</span>
            <span>{{ $invoice->customer_reference }}</span>
        </div>
    @endif
    @if ($invoice->internal_reference)
        <div class="cell">
            <span class="label">Project</span>
            <span>{{ $invoice->internal_reference }}</span>
        </div>
    @endif
</section>

<table class="lines">
    <thead>
        <tr>
            <th>Description</th>
            <th class="amount">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($lineItems as $line)
            <tr>
                <td>
                    {{ $line['description'] }}
                    @if (! empty($line['detail']))
                        <div class="muted">{{ $line['detail'] }}</div>
                    @endif
                    @if (! empty($line['reference']))
                        <div class="muted">Ref: {{ $line['reference'] }}</div>
                    @endif
                </td>
                <td class="amount">${{ number_format($line['amount_cents'] / 100, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td class="muted">No itemized lines on this invoice.</td>
                <td class="amount">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="totals">
    <tr>
        <td class="label">Subtotal</td>
        <td class="value">${{ number_format($invoice->subtotal_cents / 100, 2) }}</td>
    </tr>
    @if ($invoice->tax_cents > 0)
        <tr>
            <td class="label">Tax</td>
            <td class="value">${{ number_format($invoice->tax_cents / 100, 2) }}</td>
        </tr>
    @endif
    <tr class="grand">
        <td class="label">Total</td>
        <td class="value">${{ number_format($invoice->total_cents / 100, 2) }}</td>
    </tr>
    <tr>
        <td class="label">Paid</td>
        <td class="value">${{ number_format($invoice->paid_cents / 100, 2) }}</td>
    </tr>
    @if ($invoice->balanceCents() > 0)
        <tr class="balance">
            <td class="label">Balance due</td>
            <td class="value">${{ number_format($invoice->balanceCents() / 100, 2) }}</td>
        </tr>
    @endif
</table>

<footer class="legal">
    Generated {{ now()->format('M j, Y g:i A') }} by {{ $appName }}.
    Questions? Contact {{ $fromEmail ?: 'your account representative' }}.
</footer>

</body>
</html>
