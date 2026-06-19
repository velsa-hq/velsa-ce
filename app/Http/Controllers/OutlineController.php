<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Department;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\OutlineItemTask;
use App\Models\OutlineItemTemplate;
use App\Models\StaffAssignment;
use App\Services\SystemSettings\SystemSettings;
use App\Support\DateFormatter;
use App\Support\Markdown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class OutlineController extends Controller
{
    public function show(Booking $booking): Response
    {
        $this->authorize('view', $booking);

        $outline = EventOutline::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['published_version' => 0],
        );
        $outline->load([
            'items.space:id,name',
            'items.responsible:id,name,email',
            'items.departmentRef:key,label,color',
            'items.tasks',
        ]);
        $booking->load(['staffAssignments.user:id,name,email']);

        return Inertia::render('bookings/outline', [
            'booking' => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'name' => $booking->name,
                'start_at' => $booking->start_at?->toIso8601String(),
                'end_at' => $booking->end_at?->toIso8601String(),
                'venue_name' => $booking->venue?->name,
            ],
            'outline' => [
                'id' => $outline->id,
                'published_version' => $outline->published_version,
                'published_at' => $outline->published_at?->toIso8601String(),
                'is_published' => $outline->isPublished(),
                'notes' => $outline->notes,
            ],
            'items' => $outline->items->map(fn (OutlineItem $i) => [
                'id' => $i->id,
                'scheduled_at' => $i->scheduled_at?->toIso8601String(),
                'scheduled_at_edit' => DateFormatter::editDateTime($i->scheduled_at),
                'ends_at' => $i->endsAt()->toIso8601String(),
                'duration_minutes' => $i->duration_minutes,
                'department' => $i->department,
                'department_label' => $i->departmentLabel(),
                'department_color' => $i->departmentColor(),
                'title' => $i->title,
                'description' => $i->description,
                'description_html' => Markdown::toHtml($i->description),
                'space_name' => $i->space?->name,
                'responsible_user_id' => $i->responsible_user_id,
                'responsible_name' => $i->responsible?->name,
                'responsible_email' => $i->responsible?->email,
                'tasks' => $i->tasks->map(fn (OutlineItemTask $t) => [
                    'id' => $t->id,
                    'label' => $t->label,
                    'is_done' => $t->is_done,
                ])->all(),
            ]),
            'departments' => Department::query()->active()->ordered()->get(['key', 'label', 'color'])
                ->map(fn (Department $d) => ['value' => $d->key, 'label' => $d->label, 'color' => $d->color])
                ->all(),
            'item_templates' => OutlineItemTemplate::query()->active()->ordered()
                ->get(['id', 'label', 'department', 'default_duration_minutes', 'description', 'checklist'])
                ->map(fn (OutlineItemTemplate $t) => [
                    'id' => $t->id,
                    'label' => $t->label,
                    'department' => $t->department,
                    'default_duration_minutes' => $t->default_duration_minutes,
                    'description' => $t->description,
                    'checklist' => array_values($t->checklist ?? []),
                ])->all(),
            // responsible-user picker pool: only staff working this booking
            'staff' => $booking->staffAssignments->map(fn (StaffAssignment $a) => [
                'user_id' => $a->user_id,
                'name' => $a->user?->name,
                'email' => $a->user?->email,
                'role' => $a->role,
                'start_at' => $a->start_at?->toIso8601String(),
                'end_at' => $a->end_at?->toIso8601String(),
            ])->unique('user_id')->values()->all(),
        ]);
    }

    public function storeItem(Booking $booking, Request $request): RedirectResponse
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'department' => ['required', Rule::exists('departments', 'key')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
        ]);

        // checklist seeds the item's tasks (template prefill or typed by hand)
        $checklist = array_values(array_filter(
            $data['checklist'] ?? [],
            fn ($line) => trim((string) $line) !== '',
        ));
        unset($data['checklist']);

        $outline = EventOutline::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['published_version' => 0],
        );

        $item = OutlineItem::query()->create(array_merge($data, [
            'event_outline_id' => $outline->id,
        ]));

        foreach ($checklist as $position => $label) {
            $item->tasks()->create(['label' => $label, 'position' => $position]);
        }

        return back()->with('status', 'Item added to outline.');
    }

    public function updateItem(OutlineItem $item, Request $request): RedirectResponse
    {
        $this->authorize('update', $item->outline->booking);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'department' => ['required', Rule::exists('departments', 'key')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $item->update($data);

        return back()->with('status', 'Outline item updated.');
    }

    public function destroyItem(OutlineItem $item): RedirectResponse
    {
        $this->authorize('update', $item->outline->booking);

        $bookingId = $item->outline?->booking_id;
        $item->delete();

        return redirect("/bookings/{$bookingId}/outline")->with('status', 'Item removed.');
    }

    public function storeTask(OutlineItem $item, Request $request): RedirectResponse
    {
        $this->authorize('update', $item->outline->booking);

        $data = $request->validate(['label' => ['required', 'string', 'max:255']]);

        $item->tasks()->create([
            'label' => $data['label'],
            'position' => (int) $item->tasks()->max('position') + 1,
        ]);

        return back()->with('status', 'Checklist item added.');
    }

    public function toggleTask(OutlineItemTask $task): RedirectResponse
    {
        $this->authorize('update', $task->outlineItem->outline->booking);

        $task->update([
            'is_done' => ! $task->is_done,
            'done_at' => $task->is_done ? null : now(),
        ]);

        return back();
    }

    public function destroyTask(OutlineItemTask $task): RedirectResponse
    {
        $this->authorize('update', $task->outlineItem->outline->booking);

        $task->delete();

        return back()->with('status', 'Checklist item removed.');
    }

    /**
     * Render the run-of-show as a printable PDF run sheet. Times are
     * formatted server-side so they print as stored wall-clock with no
     * timezone shift.
     */
    public function downloadPdf(Booking $booking, SystemSettings $settings): PdfBuilder
    {
        $this->authorize('view', $booking);

        $outline = EventOutline::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['published_version' => 0],
        );
        $outline->load([
            'items.space:id,name',
            'items.responsible:id,name',
            'items.tasks',
        ]);
        $booking->load(['venue:id,name', 'client:id,name']);

        $items = $outline->items->map(fn (OutlineItem $i) => [
            'day_label' => DateFormatter::dayLabel($i->scheduled_at) ?? 'Unscheduled',
            'time' => DateFormatter::timeOnly($i->scheduled_at) ?? '-',
            'end_time' => $i->scheduled_at ? DateFormatter::timeOnly($i->endsAt()) : null,
            'duration_minutes' => $i->duration_minutes,
            'department' => $i->departmentLabel(),
            'title' => $i->title,
            'description_html' => Markdown::toHtml($i->description),
            'space' => $i->space?->name,
            'responsible' => $i->responsible?->name,
            'tasks' => $i->tasks->map(fn (OutlineItemTask $t) => [
                'label' => $t->label,
                'is_done' => $t->is_done,
            ])->all(),
        ])->all();

        return Pdf::view('pdf.run-of-show', [
            'booking' => $booking,
            'items' => $items,
            'isPublished' => $outline->isPublished(),
            'publishedVersion' => $outline->published_version,
            'appName' => (string) config('app.name'),
            'appSubtitle' => (string) $settings->get('branding.app_subtitle', ''),
        ])->name("run-of-show-{$booking->reference}.pdf");
    }

    public function publish(Booking $booking, Request $request): RedirectResponse
    {
        $this->authorize('update', $booking);

        $outline = EventOutline::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            ['published_version' => 0],
        );

        $outline->publish($request->user()?->id);

        return back()->with('status', "Outline published (v{$outline->published_version}).");
    }
}
