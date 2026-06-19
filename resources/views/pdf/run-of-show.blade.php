<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Run of show - {{ $booking->reference }}</title>
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
        header.brand .doc-meta .status {
            font-size: 8pt; margin-top: 4px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        header.brand .doc-meta .status.published { color: #047857; }
        header.brand .doc-meta .status.draft { color: #b45309; }

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

        h2.day {
            font-size: 11pt; text-transform: uppercase; letter-spacing: 0.06em;
            color: #1f2937; border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px; margin: 22px 0 8px;
        }

        table.rows {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.rows thead th {
            background: #1f2937; color: #ffffff;
            text-align: left; padding: 6px 9px;
            font-size: 8.5pt; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em;
        }
        table.rows tbody td {
            padding: 7px 9px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        td.when { white-space: nowrap; font-variant-numeric: tabular-nums; width: 16%; }
        td.when .dur { color: #6b7280; font-size: 8.5pt; }
        td.dept {
            width: 16%; font-size: 8.5pt; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.03em; color: #374151;
        }
        td.activity .title { font-weight: 600; }
        td.activity .desc { color: #4b5563; font-size: 9pt; margin-top: 2px; }
        td.activity .desc p { margin: 2px 0; }
        td.activity .desc ul, td.activity .desc ol { margin: 2px 0; padding-left: 16px; }
        td.activity .meta { color: #6b7280; font-size: 8.5pt; margin-top: 3px; }
        td.activity ul.checklist {
            list-style: none; margin: 4px 0 0; padding: 0;
            font-size: 9pt;
        }
        td.activity ul.checklist li { margin: 1px 0; }
        td.activity ul.checklist li.done { color: #6b7280; text-decoration: line-through; }
        td.empty { color: #6b7280; font-style: italic; text-align: center; padding: 16px; }

        footer.legal {
            margin-top: 28px;
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
        <div class="kind">Run of show</div>
        <div class="ref">{{ $booking->reference }}</div>
        <div class="dates">
            {{ $booking->start_at?->format('M j, Y') }}
            @if ($booking->end_at && $booking->end_at->ne($booking->start_at))
                - {{ $booking->end_at->format('M j, Y') }}
            @endif
        </div>
        @if ($isPublished)
            <div class="status published">Published · v{{ $publishedVersion }}</div>
        @else
            <div class="status draft">Draft · unpublished</div>
        @endif
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
</section>

@forelse ($items as $item)
    @if (! isset($currentDay) || $currentDay !== $item['day_label'])
        @if (isset($currentDay))
            </tbody></table>
        @endif
        @php($currentDay = $item['day_label'])
        <h2 class="day">{{ $currentDay }}</h2>
        <table class="rows">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Dept</th>
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
    @endif
        <tr>
            <td class="when">
                {{ $item['time'] }}@if ($item['end_time']) - {{ $item['end_time'] }}@endif
                <div class="dur">{{ $item['duration_minutes'] }} min</div>
            </td>
            <td class="dept">{{ $item['department'] }}</td>
            <td class="activity">
                <div class="title">{{ $item['title'] }}</div>
                @if ($item['description_html'])
                    <div class="desc">{!! $item['description_html'] !!}</div>
                @endif
                @if (! empty($item['tasks']))
                    <ul class="checklist">
                        @foreach ($item['tasks'] as $task)
                            <li class="{{ $task['is_done'] ? 'done' : '' }}">
                                {{ $task['is_done'] ? '☑' : '☐' }} {{ $task['label'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($item['responsible'] || $item['space'])
                    <div class="meta">
                        @if ($item['responsible']) Responsible: {{ $item['responsible'] }} @endif
                        @if ($item['responsible'] && $item['space']) · @endif
                        @if ($item['space']) Space: {{ $item['space'] }} @endif
                    </div>
                @endif
            </td>
        </tr>
    @if ($loop->last)
        </tbody></table>
    @endif
@empty
    <table class="rows"><tbody>
        <tr><td class="empty">No items in this run of show yet.</td></tr>
    </tbody></table>
@endforelse

<footer class="legal">
    Run sheet generated {{ now()->format('M j, Y g:i A') }} by {{ $appName }}.
</footer>

</body>
</html>
