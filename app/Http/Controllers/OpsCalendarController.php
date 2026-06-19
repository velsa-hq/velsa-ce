<?php

namespace App\Http\Controllers;

use App\Services\Operations\OpsCalendarBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FullCalendar view of bookings and blackouts across venues. Companion to
 * /ops/schedule (per-space timeline).
 */
class OpsCalendarController extends Controller
{
    public function index(Request $request, OpsCalendarBuilder $builder): Response
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('bookings.view'), 403);

        $venueId = $request->integer('venue_id') ?: null;

        // surrounding 3 months so the initial paint has events ready
        // regardless of which view FullCalendar boots into
        $start = CarbonImmutable::today()->subMonth()->startOfMonth();
        $end = CarbonImmutable::today()->addMonths(2)->endOfMonth();

        $data = $builder->build($start, $end, $venueId);

        return Inertia::render('ops/calendar', [
            'events' => $data['events'],
            'venues' => $data['venues'],
            'venue_id' => $venueId,
            'window' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
        ]);
    }
}
