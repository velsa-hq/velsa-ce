<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory count sheet</title>
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
        header.brand .doc-meta .dates { font-size: 9pt; color: #6b7280; margin-top: 2px; }

        .counter-line { margin: 0 0 14px; font-size: 9pt; color: #374151; }
        .counter-line .blank { display: inline-block; min-width: 180px; border-bottom: 1px solid #9ca3af; }

        h2.venue {
            font-size: 11pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #1f2937; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px; margin: 18px 0 8px;
        }
        table.rows { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.rows thead th {
            background: #1f2937; color: #fff; text-align: left;
            padding: 6px 8px; font-size: 8.5pt; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        table.rows thead th.right { text-align: right; }
        table.rows tbody td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        table.rows tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.rows tbody td.count-col { width: 90px; border-left: 1px dashed #9ca3af; }
        table.rows tbody td.notes-col { width: 150px; }
        td.empty { color: #6b7280; font-style: italic; text-align: center; padding: 14px; }

        footer.legal {
            margin-top: 26px; border-top: 1px solid #e5e7eb;
            padding-top: 10px; color: #6b7280; font-size: 8.5pt;
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
        <div class="kind">Inventory count sheet</div>
        <div class="dates">Generated {{ $generatedAt }}</div>
    </div>
</header>

<p class="counter-line">
    Counted by <span class="blank">&nbsp;</span>
    &nbsp;&nbsp; Date <span class="blank">&nbsp;</span>
</p>

@php($currentVenue = null)
@forelse ($rows as $row)
    @if ($currentVenue !== $row['venue'])
        @if ($currentVenue !== null)
            </tbody></table>
        @endif
        @php($currentVenue = $row['venue'])
        <h2 class="venue">{{ $currentVenue }}</h2>
        <table class="rows">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>SKU</th>
                    <th>Kind</th>
                    <th class="right">On hand (system)</th>
                    <th class="count-col">Counted</th>
                    <th class="notes-col">Notes</th>
                </tr>
            </thead>
            <tbody>
    @endif
        <tr>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['sku'] ?? '-' }}</td>
            <td>{{ $row['kind'] }}</td>
            <td class="right">{{ $row['on_hand'] }} / {{ $row['total'] }}</td>
            <td class="count-col">&nbsp;</td>
            <td class="notes-col">&nbsp;</td>
        </tr>
    @if ($loop->last)
        </tbody></table>
    @endif
@empty
    <p class="empty">No resources match the current filter.</p>
@endforelse

<footer class="legal">
    System on-hand reflects the count at generation time. Record the
    physical count, note any variance, and reconcile in {{ $appName }}.
</footer>

</body>
</html>
