<?php

namespace App\Reports\Handlers;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;

/**
 * Booking status transitions over a recent window (default 24h),
 * sourced from the append-only audit_events table.
 */
class EventStatusChangeReport implements ReportHandler
{
    public function slug(): string
    {
        return 'event-status-changes';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Event status changes';
    }

    public function description(): string
    {
        return 'Every booking status transition in the lookback window, sourced from the append-only audit log. Defaults to the last 24 hours.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'hours', 'label' => 'Lookback (hours)', 'type' => 'number', 'default' => 24],
        ];
    }

    public function run(array $params): ReportResult
    {
        $hours = isset($params['hours']) ? max(1, (int) $params['hours']) : 24;
        $since = now()->subHours($hours);

        $events = AuditEvent::query()
            ->where('subject_type', Booking::class)
            ->where('event_type', 'booking.updated')
            ->where('created_at', '>=', $since)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (AuditEvent $e) => $this->isStatusChange($e));

        $bookingIds = $events->pluck('subject_id')->unique()->all();
        $bookings = Booking::query()
            ->whereIn('id', $bookingIds)
            ->with('venue:id,name')
            ->get()
            ->keyBy('id');

        $rows = $events->values()->map(function (AuditEvent $e) use ($bookings) {
            $booking = $bookings->get($e->subject_id);
            $payload = $e->payload_json ?? [];

            return [
                'at' => $e->created_at?->format('M j, Y g:i A'),
                'reference' => $booking?->reference ?? '#'.$e->subject_id,
                'event' => $booking?->name ?? '-',
                'venue' => $booking?->venue?->name ?? '-',
                'from' => $payload['before']['status'] ?? '-',
                'to' => $payload['after']['status'] ?? '-',
                'by' => $e->user?->name ?? 'system',
            ];
        })->all();

        $byTransition = [];
        foreach ($rows as $r) {
            $key = $r['from'].' -> '.$r['to'];
            $byTransition[$key] = ($byTransition[$key] ?? 0) + 1;
        }

        $summary = [
            ['label' => 'Status changes', 'value' => (string) count($rows)],
            ['label' => 'Distinct bookings', 'value' => (string) count(array_unique(array_column($rows, 'reference')))],
        ];
        foreach ($byTransition as $label => $count) {
            $summary[] = ['label' => $label, 'value' => (string) $count];
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('Last %d hours (since %s)', $hours, $since->toFormattedDateString()),
            columns: [
                ['key' => 'at', 'label' => 'When'],
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'event', 'label' => 'Event'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'from', 'label' => 'From'],
                ['key' => 'to', 'label' => 'To'],
                ['key' => 'by', 'label' => 'By'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * Auditable writes booking.updated rows as {before: {...}, after: {...}}.
     * A status change is when 'status' appears in 'after'.
     */
    protected function isStatusChange(AuditEvent $event): bool
    {
        $payload = $event->payload_json ?? [];

        return is_array($payload['after'] ?? null) && array_key_exists('status', $payload['after']);
    }
}
