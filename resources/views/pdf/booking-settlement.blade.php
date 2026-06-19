<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settlement - {{ $booking->reference }}</title>
    <style>
        @page { margin: 1.25cm 1cm; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 10pt;
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
        header.brand .org-name { font-size: 16pt; font-weight: 700; }
        header.brand .org-sub { font-size: 9pt; color: #6b7280; margin-top: 2px; }
        header.brand .doc-meta { text-align: right; }
        header.brand .doc-meta .kind {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600;
        }
        header.brand .doc-meta .ref { font-size: 14pt; font-weight: 700; }
        header.brand .doc-meta .dates { font-size: 9pt; color: #6b7280; margin-top: 2px; }

        section.event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            padding: 12px 14px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 22px;
        }
        section.event-meta .cell { min-width: 30%; }
        section.event-meta .label {
            font-size: 8pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; display: block;
        }

        h2.section {
            font-size: 11pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #1f2937; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px; margin: 22px 0 10px;
        }

        table.rows {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.rows thead th {
            background: #1f2937; color: #ffffff;
            text-align: left; padding: 7px 9px;
            font-size: 8.5pt; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        table.rows thead th.right { text-align: right; }
        table.rows tbody td {
            padding: 6px 9px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.rows tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.rows tbody td.muted { color: #6b7280; }
        table.rows tbody tr.subtotal td {
            font-weight: 700; border-top: 2px solid #1f2937;
            border-bottom: none; background: #f9fafb;
        }
        table.rows tbody td.empty {
            color: #6b7280; font-style: italic; text-align: center; padding: 14px;
        }

        section.settlement {
            margin-top: 28px;
            background: #f9fafb;
            border: 2px solid #1f2937;
            border-radius: 6px;
            padding: 14px 18px;
        }
        section.settlement table { width: 100%; font-variant-numeric: tabular-nums; }
        section.settlement table td { padding: 4px 0; }
        section.settlement table td.label { color: #374151; }
        section.settlement table td.value { text-align: right; font-weight: 600; }
        section.settlement tr.net td {
            border-top: 2px solid #1f2937; padding-top: 8px;
            font-size: 12pt; font-weight: 700;
        }
        section.settlement tr.outstanding td.value { color: #991b1b; }

        footer.legal {
            margin-top: 32px;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            color: #6b7280;
            font-size: 8.5pt;
        }
    </style>
</head>
<body>

<header class="brand">
    <div>
        <div class="org-name">{{ $appName }}</div>
        <div class="org-sub">{{ $appSubtitle }}</div>
    </div>
    <div class="doc-meta">
        <div class="kind">Event settlement</div>
        <div class="ref">{{ $booking->reference }}</div>
        <div class="dates">
            {{ $booking->start_at?->format('M j, Y') }}
            @if ($booking->end_at && $booking->end_at->ne($booking->start_at))
                - {{ $booking->end_at->format('M j, Y') }}
            @endif
        </div>
    </div>
</header>

<section class="event-meta">
    <div class="cell">
        <span class="label">Event</span>
        <span>{{ $booking->name ?? '-' }}</span>
    </div>
    <div class="cell">
        <span class="label">Venue</span>
        <span>{{ $booking->venue?->name ?? '-' }}</span>
    </div>
    <div class="cell">
        <span class="label">Client</span>
        <span>{{ $booking->client?->name ?? '-' }}</span>
    </div>
    <div class="cell">
        <span class="label">Attendance (est. / actual)</span>
        <span>
            {{ $booking->attendance_estimate ?? '-' }}
            /
            {{ $booking->attendance_actual ?? '-' }}
        </span>
    </div>
    <div class="cell">
        <span class="label">Status</span>
        <span>{{ $booking->status?->value }}</span>
    </div>
</section>

<h2 class="section">Charges</h2>
<table class="rows">
    <thead>
        <tr>
            <th>Item</th>
            <th class="right">Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($charges as $charge)
            <tr>
                <td>
                    {{ $charge['label'] }}
                    @if (! empty($charge['detail']))
                        <div class="muted">{{ $charge['detail'] }}</div>
                    @endif
                </td>
                <td class="right">${{ number_format($charge['amount_cents'] / 100, 2) }}</td>
            </tr>
        @empty
            <tr><td class="empty" colspan="2">No charges recorded.</td></tr>
        @endforelse
        @if (! empty($charges))
            <tr class="subtotal">
                <td>Subtotal</td>
                <td class="right">${{ number_format($charges_subtotal_cents / 100, 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>

<h2 class="section">Invoices issued</h2>
<table class="rows">
    <thead>
        <tr>
            <th>Number</th>
            <th>Source</th>
            <th>Issued</th>
            <th>Status</th>
            <th class="right">Total</th>
            <th class="right">Paid</th>
            <th class="right">Balance</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoices as $inv)
            <tr>
                <td>{{ $inv['number'] }}</td>
                <td>{{ $inv['source'] }}</td>
                <td>{{ $inv['issued_on'] ?? '-' }}</td>
                <td>{{ $inv['status'] ?? '-' }}</td>
                <td class="right">${{ number_format($inv['total_cents'] / 100, 2) }}</td>
                <td class="right">${{ number_format($inv['paid_cents'] / 100, 2) }}</td>
                <td class="right">${{ number_format($inv['balance_cents'] / 100, 2) }}</td>
            </tr>
        @empty
            <tr><td class="empty" colspan="7">No invoices issued for this booking.</td></tr>
        @endforelse
    </tbody>
</table>

<h2 class="section">Payments received</h2>
<table class="rows">
    <thead>
        <tr>
            <th>Date</th>
            <th>Method</th>
            <th class="right">Amount</th>
            <th class="right">Refunded</th>
            <th class="right">Net</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($payments as $p)
            <tr>
                <td>{{ $p['date'] ?? '-' }}</td>
                <td>{{ $p['method'] }}</td>
                <td class="right">${{ number_format($p['amount_cents'] / 100, 2) }}</td>
                <td class="right">${{ number_format($p['refunded_cents'] / 100, 2) }}</td>
                <td class="right">${{ number_format(($p['amount_cents'] - $p['refunded_cents']) / 100, 2) }}</td>
            </tr>
        @empty
            <tr><td class="empty" colspan="5">No payments recorded.</td></tr>
        @endforelse
    </tbody>
</table>

<section class="settlement">
    <table>
        <tr>
            <td class="label">Total invoiced</td>
            <td class="value">${{ number_format($totals['invoiced_cents'] / 100, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Total payments received</td>
            <td class="value">${{ number_format($totals['paid_cents'] / 100, 2) }}</td>
        </tr>
        @if ($totals['refunded_cents'] > 0)
            <tr>
                <td class="label">Refunds posted</td>
                <td class="value">(${{ number_format($totals['refunded_cents'] / 100, 2) }})</td>
            </tr>
        @endif
        <tr class="net">
            <td class="label">Net collected</td>
            <td class="value">${{ number_format($totals['net_collected_cents'] / 100, 2) }}</td>
        </tr>
        @if ($totals['outstanding_cents'] > 0)
            <tr class="outstanding">
                <td class="label">Outstanding balance</td>
                <td class="value">${{ number_format($totals['outstanding_cents'] / 100, 2) }}</td>
            </tr>
        @endif
    </table>
</section>

<footer class="legal">
    Settlement generated {{ now()->format('M j, Y g:i A') }} by {{ $appName }}.
    This document reconciles every charge, invoice, and payment associated with the event named above.
</footer>

</body>
</html>
