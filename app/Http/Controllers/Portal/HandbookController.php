<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Exhibitor;
use App\Models\ExhibitorEvent;
use App\Models\ExhibitorHandbookAcknowledgement;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandbookController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $venue = $this->resolveVenue($exhibitor);

        if ($venue === null || ! $venue->hasPublishedExhibitorHandbook()) {
            return redirect('/portal')->with('toast', [
                'type' => 'info',
                'message' => 'No exhibitor handbook is published for your venue yet.',
            ]);
        }

        $ack = ExhibitorHandbookAcknowledgement::query()
            ->where('exhibitor_id', $exhibitor->id)
            ->where('venue_id', $venue->id)
            ->first();

        return Inertia::render('portal/handbook', [
            'venue_name' => $venue->name,
            'handbook_html' => $venue->exhibitorHandbookHtml(),
            'acknowledged_at' => $ack?->acknowledged_at?->toIso8601String(),
        ]);
    }

    public function acknowledge(Request $request): RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $venue = $this->resolveVenue($exhibitor);
        abort_if($venue === null || ! $venue->hasPublishedExhibitorHandbook(), 404);

        // idempotent: keep the first acknowledgement date
        ExhibitorHandbookAcknowledgement::query()->firstOrCreate(
            ['exhibitor_id' => $exhibitor->id, 'venue_id' => $venue->id],
            ['acknowledged_at' => now()],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Handbook acknowledged - thank you.']);
    }

    private function resolveVenue(Exhibitor $exhibitor): ?Venue
    {
        $event = ExhibitorEvent::find($exhibitor->exhibitor_event_id);
        $booking = $event !== null ? Booking::find($event->booking_id) : null;

        return $booking !== null ? Venue::find($booking->venue_id) : null;
    }
}
