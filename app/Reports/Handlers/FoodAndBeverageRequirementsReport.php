<?php

namespace App\Reports\Handlers;

use App\Models\OutlineItem;
use App\Models\Venue;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use Illuminate\Support\Carbon;

/**
 * Catering outline items across a date window, sorted by date.
 */
class FoodAndBeverageRequirementsReport implements ReportHandler
{
    public function slug(): string
    {
        return 'food-and-beverage-requirements';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Food & beverage requirements';
    }

    public function description(): string
    {
        return 'Every Catering outline item scheduled in the window, with event, venue, space, time, and attendance estimate. The caterer\'s prep list.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(14)->toDateString()],
            ['key' => 'venue_id', 'label' => 'Venue', 'type' => 'select', 'options' => $this->venueOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->addDays(14)->endOfDay();
        $venueId = isset($params['venue_id']) && $params['venue_id'] !== '' ? (int) $params['venue_id'] : null;

        $items = OutlineItem::query()
            ->forDepartment('catering')
            ->whereBetween('scheduled_at', [$from, $to])
            ->with([
                'outline.booking:id,reference,name,venue_id,attendance_estimate',
                'outline.booking.venue:id,name,slug',
                'space:id,name',
            ])
            ->when($venueId, fn ($q, $v) => $q->whereHas('outline.booking', fn ($qq) => $qq->where('venue_id', $v)))
            ->orderBy('scheduled_at')
            ->get();

        $rows = $items->map(function (OutlineItem $i) {
            $booking = $i->outline?->booking;

            return [
                'scheduled_at' => $i->scheduled_at?->format('M j, Y g:i A'),
                'duration_min' => $i->duration_minutes,
                'title' => $i->title,
                'event' => $booking?->name ?? '-',
                'reference' => $booking?->reference ?? '-',
                'venue' => $booking?->venue?->name ?? '-',
                'space' => $i->space?->name ?? '-',
                'attendance' => $booking?->attendance_estimate ?? '-',
                'notes' => $i->notes ?? '',
            ];
        })->all();

        $totalAttendance = $items->sum(fn (OutlineItem $i) => (int) ($i->outline?->booking?->attendance_estimate ?? 0));
        $uniqueEvents = $items->pluck('outline.booking_id')->unique()->filter()->count();

        $summary = [
            ['label' => 'F&B touchpoints', 'value' => (string) $items->count()],
            ['label' => 'Events covered', 'value' => (string) $uniqueEvents],
            ['label' => 'Cumulative covers', 'value' => number_format($totalAttendance), 'hint' => 'sum of attendance estimates'],
        ];

        return new ReportResult(
            title: $this->title(),
            description: sprintf('Catering load %s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'scheduled_at', 'label' => 'When'],
                ['key' => 'duration_min', 'label' => 'Min', 'align' => 'right'],
                ['key' => 'title', 'label' => 'Item'],
                ['key' => 'event', 'label' => 'Event'],
                ['key' => 'reference', 'label' => 'Ref'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'space', 'label' => 'Space'],
                ['key' => 'attendance', 'label' => 'Att.', 'align' => 'right'],
                ['key' => 'notes', 'label' => 'Notes'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected function venueOptions(): array
    {
        return Venue::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['value' => (int) $v->id, 'label' => $v->name])
            ->all();
    }
}
