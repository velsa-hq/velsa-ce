<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\OutlineItem;
use App\Models\OutlineItemTask;
use App\Models\Venue;
use App\Support\DateFormatter;
use App\Support\Markdown;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpsBoardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('bookings.view'), 403);

        $days = max(7, min(28, $request->integer('days') ?: 14));
        $venueId = $request->integer('venue_id') ?: null;
        $deptFilter = $request->string('department')->toString() ?: null;

        $from = now()->startOfDay();
        $to = $from->copy()->addDays($days);

        $items = OutlineItem::query()
            ->with([
                'outline:id,booking_id,published_at',
                'outline.booking:id,reference,name,venue_id',
                'outline.booking.venue:id,name,slug',
                'space:id,name',
                'responsible:id,email',
                'tasks',
            ])
            ->withCount([
                'tasks as task_total',
                'tasks as task_done' => fn ($q) => $q->where('is_done', true),
            ])
            ->between($from, $to)
            ->whereHas('outline', fn ($q) => $q->whereNotNull('published_at'))
            ->when($venueId, fn ($q, $v) => $q->whereHas('outline.booking', fn ($qq) => $qq->where('venue_id', $v)))
            ->when($deptFilter, fn ($q, $v) => $q->where('department', $v))
            ->orderBy('scheduled_at')
            ->get();

        // active departments are the board columns, in admin-defined order
        $activeDepartments = Department::query()->active()->ordered()->get(['key', 'label', 'color']);

        // grid: date string x department -> items[]
        $dateKeys = [];
        for ($i = 0; $i < $days; $i++) {
            $dateKeys[] = $from->copy()->addDays($i)->toDateString();
        }

        $columnKeys = $activeDepartments->pluck('key');
        if ($deptFilter !== null) {
            $columnKeys = $columnKeys->filter(fn ($k) => $k === $deptFilter);
        }
        $columnKeys = $columnKeys->values();

        $grid = [];
        foreach ($dateKeys as $date) {
            $grid[$date] = [];
            foreach ($columnKeys as $key) {
                $grid[$date][$key] = [];
            }
        }

        foreach ($items as $item) {
            $date = $item->scheduled_at?->toDateString();
            $dept = (string) $item->department;
            if (! isset($grid[$date]) || ! isset($grid[$date][$dept])) {
                continue;
            }
            $grid[$date][$dept][] = [
                'id' => $item->id,
                'scheduled_at' => DateFormatter::timeOnly($item->scheduled_at),
                'scheduled_at_edit' => DateFormatter::editDateTime($item->scheduled_at),
                'duration_minutes' => $item->duration_minutes,
                'department' => $dept,
                'title' => $item->title,
                'description' => $item->description,
                'description_html' => Markdown::toHtml($item->description),
                'task_total' => $item->task_total,
                'task_done' => $item->task_done,
                'tasks' => $item->tasks->map(fn (OutlineItemTask $t) => [
                    'id' => $t->id,
                    'label' => $t->label,
                    'is_done' => $t->is_done,
                ])->all(),
                'space_name' => $item->space?->name,
                'responsible_email' => $item->responsible?->email,
                'booking' => [
                    'id' => $item->outline?->booking?->id,
                    'reference' => $item->outline?->booking?->reference,
                    'name' => $item->outline?->booking?->name,
                    'venue_name' => $item->outline?->booking?->venue?->name,
                ],
            ];
        }

        $departments = $activeDepartments
            ->when($deptFilter, fn ($c) => $c->filter(fn ($d) => $d->key === $deptFilter))
            ->map(fn (Department $d) => ['value' => $d->key, 'label' => $d->label, 'color' => $d->color])
            ->values()
            ->all();

        return Inertia::render('ops/board', [
            'grid' => $grid,
            'date_keys' => $dateKeys,
            'departments' => $departments,
            'filters' => ['days' => $days, 'venue_id' => $venueId, 'department' => $deptFilter],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'department_options' => $activeDepartments
                ->map(fn (Department $d) => ['value' => $d->key, 'label' => $d->label])
                ->values()
                ->all(),
            'total_items' => $items->count(),
        ]);
    }
}
