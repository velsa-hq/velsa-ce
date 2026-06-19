<?php

namespace App\Reports\Handlers;

use App\Models\Department;
use App\Models\OutlineItem;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\DateFormatter;
use Illuminate\Support\Carbon;

/**
 * Service-department schedule pulled from run-of-show outline items. Each row
 * is one scheduled service slot (setup, AV, catering, security, etc.) across
 * all bookings in the window - the operations-side companion to event-schedule.
 */
class EventServicesScheduleReport implements ReportHandler
{
    public function slug(): string
    {
        return 'event-services-schedule';
    }

    public function category(): string
    {
        return 'Operations';
    }

    public function title(): string
    {
        return 'Event services schedule';
    }

    public function description(): string
    {
        return 'Every scheduled service slot across all bookings in the window - setup, AV, catering, security, etc. Sourced from run-of-show outline items.';
    }

    public function parameters(): array
    {
        return [
            ['key' => 'from', 'label' => 'From', 'type' => 'date', 'default' => now()->toDateString()],
            ['key' => 'to', 'label' => 'To', 'type' => 'date', 'default' => now()->addDays(14)->toDateString()],
            ['key' => 'department', 'label' => 'Department', 'type' => 'select', 'options' => $this->departmentOptions()],
        ];
    }

    public function run(array $params): ReportResult
    {
        $from = isset($params['from']) ? Carbon::parse($params['from'])->startOfDay() : now()->startOfDay();
        $to = isset($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->addDays(14)->endOfDay();
        $department = $params['department'] ?? null;

        $items = OutlineItem::query()
            ->with([
                'outline.booking:id,reference,name,venue_id',
                'outline.booking.venue:id,name',
                'space:id,name',
                'responsible:id,name',
                'departmentRef:key,label,color',
            ])
            ->between($from, $to)
            ->when(
                $department,
                fn ($q, $d) => $q->where('department', $d),
            )
            ->orderBy('scheduled_at')
            ->get();

        $rows = $items->map(function (OutlineItem $i) {
            $booking = $i->outline?->booking;

            return [
                'date' => $i->scheduled_at?->format('M j, Y'),
                'time' => DateFormatter::timeOnly($i->scheduled_at),
                'duration' => $i->duration_minutes.' min',
                'department' => $i->departmentLabel(),
                'title' => $i->title,
                'booking' => $booking?->name ?? $booking?->reference ?? '-',
                'venue' => $booking?->venue?->name ?? '-',
                'space' => $i->space?->name ?? '-',
                'responsible' => $i->responsible?->name ?? '-',
            ];
        })->all();

        // counts per department for the summary chips
        $byDept = [];
        foreach ($items as $i) {
            $label = $i->departmentLabel();
            $byDept[$label] = ($byDept[$label] ?? 0) + 1;
        }

        $summary = [
            ['label' => 'Scheduled slots', 'value' => (string) $items->count()],
        ];
        foreach ($byDept as $label => $count) {
            $summary[] = ['label' => $label, 'value' => (string) $count];
        }

        return new ReportResult(
            title: $this->title(),
            description: sprintf('%s through %s', $from->toFormattedDateString(), $to->toFormattedDateString()),
            columns: [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'time', 'label' => 'Time'],
                ['key' => 'duration', 'label' => 'Duration'],
                ['key' => 'department', 'label' => 'Dept.'],
                ['key' => 'title', 'label' => 'Task'],
                ['key' => 'booking', 'label' => 'Event'],
                ['key' => 'venue', 'label' => 'Venue'],
                ['key' => 'space', 'label' => 'Space'],
                ['key' => 'responsible', 'label' => 'Responsible'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function departmentOptions(): array
    {
        return Department::query()->active()->ordered()->get(['key', 'label'])
            ->map(fn (Department $d) => ['value' => $d->key, 'label' => $d->label])
            ->all();
    }
}
