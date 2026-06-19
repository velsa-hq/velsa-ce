<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Work orders</title>
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
        .wo { padding-bottom: 8px; }
        .wo + .wo { page-break-before: always; }

        header.brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        header.brand .org-name { font-size: 15pt; font-weight: 700; }
        header.brand .org-sub { font-size: 9pt; color: #6b7280; margin-top: 2px; }
        header.brand .doc-meta { text-align: right; }
        header.brand .doc-meta .kind {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600;
        }
        header.brand .doc-meta .ref { font-size: 14pt; font-weight: 700; }
        header.brand .doc-meta .status { font-size: 9pt; color: #6b7280; margin-top: 2px; }

        h1.title { font-size: 13pt; margin: 0 0 10px; }

        section.meta {
            display: flex; flex-wrap: wrap; gap: 12px;
            padding: 10px 12px; background: #f9fafb;
            border: 1px solid #e5e7eb; border-radius: 6px; margin-bottom: 16px;
        }
        section.meta .cell { min-width: 30%; }
        section.meta .label {
            font-size: 8pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; display: block;
        }

        h2.section {
            font-size: 10pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #1f2937; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px; margin: 16px 0 8px;
        }
        p.desc { white-space: pre-wrap; margin: 0 0 8px; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items thead th {
            background: #1f2937; color: #fff; text-align: left;
            padding: 6px 8px; font-size: 8.5pt; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        table.items thead th.right { text-align: right; }
        table.items tbody td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.items tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.items tbody td.empty { color: #6b7280; font-style: italic; text-align: center; padding: 12px; }
        table.items tr.subtotal td { font-weight: 700; border-top: 2px solid #1f2937; border-bottom: none; }

        .signoff { margin-top: 28px; display: flex; gap: 40px; }
        .signoff .line { flex: 1; border-top: 1px solid #9ca3af; padding-top: 4px; font-size: 8.5pt; color: #6b7280; }
    </style>
</head>
<body>

@forelse ($orders as $wo)
    <div class="wo">
        <header class="brand">
            <div>
                <div class="org-name">{{ $appName }}</div>
                <div class="org-sub">{{ $appSubtitle }}</div>
            </div>
            <div class="doc-meta">
                <div class="kind">Work order · {{ $wo['kind'] }}</div>
                <div class="ref">{{ $wo['reference'] }}</div>
                <div class="status">{{ $wo['status'] }} · P{{ $wo['priority'] }}</div>
            </div>
        </header>

        <h1 class="title">{{ $wo['title'] }}</h1>

        <section class="meta">
            <div class="cell"><span class="label">Venue</span><span>{{ $wo['venue'] ?? '-' }}</span></div>
            <div class="cell"><span class="label">Scheduled</span><span>{{ $wo['scheduled_for'] }}</span></div>
            <div class="cell"><span class="label">Assignee</span><span>{{ $wo['assignee'] ?? 'Unassigned' }}</span></div>
            <div class="cell"><span class="label">Requested by</span><span>{{ $wo['requester'] ?? '-' }}</span></div>
            <div class="cell"><span class="label">Cost</span><span>{{ $wo['cost'] ?? '-' }}</span></div>
            @if ($wo['completed_at'])
                <div class="cell"><span class="label">Completed</span><span>{{ $wo['completed_at'] }}</span></div>
            @endif
        </section>

        @if ($wo['description'])
            <h2 class="section">Description</h2>
            <p class="desc">{{ $wo['description'] }}</p>
        @endif

        <h2 class="section">Items</h2>
        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Action</th>
                    <th class="right">Qty</th>
                    <th class="right">Line cost</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($wo['items'] as $item)
                    <tr>
                        <td>
                            {{ $item['name'] }}
                            @if ($item['sku'])
                                <span style="color:#6b7280;">· {{ $item['sku'] }}</span>
                            @endif
                        </td>
                        <td>{{ $item['action'] }}</td>
                        <td class="right">{{ $item['quantity'] }}{{ $item['unit'] ? ' '.$item['unit'] : '' }}</td>
                        <td class="right">{{ $item['line'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="empty" colspan="4">No items.</td></tr>
                @endforelse
                @if ($wo['items_subtotal'])
                    <tr class="subtotal">
                        <td colspan="3">Items subtotal</td>
                        <td class="right">{{ $wo['items_subtotal'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <div class="signoff">
            <div class="line">Completed by / date</div>
            <div class="line">Verified by / date</div>
        </div>
    </div>
@empty
    <p style="color:#6b7280;font-style:italic;">No work orders match the current filter.</p>
@endforelse

</body>
</html>
