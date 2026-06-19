<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Diagram;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiagramController extends Controller
{
    public function show(Booking $booking, Request $request): Response
    {
        $this->authorize('view', $booking);

        // ?space_id= or fall back to the booking's first space
        $spaceId = $request->integer('space_id')
            ?: $booking->spaces()->value('space_id');

        $space = $spaceId ? Space::query()->findOrFail($spaceId) : null;
        abort_unless($space !== null, 404, 'This booking has no spaces yet.');

        $diagram = Diagram::query()
            ->with('currentVersion')
            ->firstOrCreate(
                ['booking_id' => $booking->id, 'space_id' => $space->id],
                ['name' => "{$booking->reference} · {$space->name}", 'scale_px_per_foot' => 10],
            );

        $objects = $diagram->currentVersion?->objects_json ?? [];

        return Inertia::render('bookings/diagram', [
            'booking' => [
                'id' => $booking->id,
                'reference' => $booking->reference,
                'name' => $booking->name,
                'attendance_estimate' => $booking->attendance_estimate,
            ],
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'sqft' => $space->sqft,
                'capacity' => $space->capacity,
                // permanent infrastructure; non-draggable backdrop, managed at /admin/spaces/{space}/constraints
                'constraints' => $space->constraints_json ?? [],
                // optional scale drawing behind the grid
                'floorplan_url' => $space->floorPlanUrl(),
            ],
            'diagram' => [
                'id' => $diagram->id,
                'name' => $diagram->name,
                'scale_px_per_foot' => $diagram->scale_px_per_foot,
                'version' => $diagram->currentVersion?->version,
                'is_locked' => $diagram->isLocked(),
            ],
            'objects' => $objects,
            'sibling_spaces' => $booking->spaces()
                ->with('space:id,name,kind')
                ->get()
                ->pluck('space')
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
                ->all(),
            'templates' => LayoutTemplateController::availableFor($space),
        ]);
    }

    public function store(Booking $booking, Request $request): RedirectResponse
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'objects' => ['present', 'array'],
            'objects.*.type' => ['required', 'string'],
            'objects.*.x' => ['required', 'numeric'],
            'objects.*.y' => ['required', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $diagram = Diagram::query()->firstOrCreate(
            ['booking_id' => $booking->id, 'space_id' => $data['space_id']],
            ['name' => "{$booking->reference}", 'scale_px_per_foot' => 10],
        );

        abort_if($diagram->isLocked(), 423, 'Diagram is locked.');

        $diagram->saveVersion(
            objects: $data['objects'],
            userId: $request->user()?->id,
            note: $data['note'] ?? null,
        );

        return back()->with('status', 'Diagram saved.');
    }
}
