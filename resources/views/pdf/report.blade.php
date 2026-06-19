<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $result->title }}</title>
    <style>
        @page { margin: 1cm 0.9cm 1.4cm 0.9cm; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 9pt;
            line-height: 1.4;
            margin: 0;
        }

        header.brand {
            border-bottom: 2px solid #1f2937;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        header.brand .top {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 6px;
        }
        header.brand .org-name { font-size: 12pt; font-weight: 700; }
        header.brand .category {
            font-size: 8pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600;
        }
        header.brand h1 {
            font-size: 16pt; font-weight: 700;
            margin: 0; line-height: 1.2;
        }
        header.brand .desc { color: #6b7280; margin-top: 2px; }

        section.meta {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            padding: 8px 10px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            margin-bottom: 14px;
            font-size: 8.5pt;
        }
        section.meta .cell .label {
            font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; display: block;
        }

        section.summary {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }
        section.summary .stat {
            flex: 1 1 calc(25% - 10px);
            min-width: 110px;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background: #ffffff;
        }
        section.summary .stat .label {
            font-size: 7pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600; display: block; margin-bottom: 2px;
        }
        section.summary .stat .value {
            font-size: 13pt; font-weight: 700; font-variant-numeric: tabular-nums;
        }

        table.rows {
            width: 100%;
            border-collapse: collapse;
        }
        table.rows thead th {
            background: #1f2937;
            color: #ffffff;
            text-align: left;
            padding: 6px 7px;
            font-size: 8pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        table.rows thead th.right { text-align: right; }
        table.rows tbody td {
            padding: 5px 7px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        table.rows tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
        table.rows tbody tr:nth-child(even) td { background: #fafafa; }
        table.rows tbody td.empty {
            color: #6b7280; font-style: italic; text-align: center; padding: 18px;
        }

        footer.legal {
            position: fixed;
            bottom: -0.8cm;
            left: 0; right: 0;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 7.5pt;
            color: #6b7280;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

<header class="brand">
    <div class="top">
        <span class="org-name">{{ $appName }}</span>
        <span class="category">{{ $handler->category() }} · Report</span>
    </div>
    <h1>{{ $result->title }}</h1>
    @if ($result->description)
        <div class="desc">{{ $result->description }}</div>
    @endif
</header>

@if (! empty($paramRows))
    <section class="meta">
        @foreach ($paramRows as $row)
            <div class="cell">
                <span class="label">{{ $row['label'] }}</span>
                <span>{{ $row['value'] }}</span>
            </div>
        @endforeach
    </section>
@endif

@if (! empty($result->summary))
    <section class="summary">
        @foreach ($result->summary as $stat)
            <div class="stat">
                <span class="label">{{ $stat['label'] ?? '' }}</span>
                <span class="value">{{ $stat['value'] ?? '' }}</span>
            </div>
        @endforeach
    </section>
@endif

<table class="rows">
    <thead>
        <tr>
            @foreach ($result->columns as $col)
                @php
                    $align = ($col['align'] ?? null) === 'right' ? 'right' : '';
                @endphp
                <th class="{{ $align }}">{{ $col['label'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($result->rows as $row)
            <tr>
                @foreach ($result->columns as $col)
                    @php
                        $align = ($col['align'] ?? null) === 'right' ? 'right' : '';
                        $cell = $row[$col['key']] ?? '';
                    @endphp
                    <td class="{{ $align }}">{{ $cell }}</td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td class="empty" colspan="{{ count($result->columns) }}">No rows.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<footer class="legal">
    <span>Generated {{ $generatedAt }} by {{ $appName }}</span>
    <span>{{ count($result->rows) }} {{ count($result->rows) === 1 ? 'row' : 'rows' }}</span>
</footer>

</body>
</html>
