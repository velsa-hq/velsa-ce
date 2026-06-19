<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Operations\OpsScheduleBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Two-week per-location schedule (spaces as rows, days as columns).
 * Window defaults to today; ?from=YYYY-MM-DD pages or jumps the start.
 */
class OpsScheduleController extends Controller
{
    public function index(Request $request, OpsScheduleBuilder $builder): Response
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('bookings.view'), 403);

        $from = $this->resolveStart($request);
        $venueId = $request->integer('venue_id') ?: null;

        $grid = $builder->build($from, $venueId);

        return Inertia::render('ops/schedule', [
            'window_start' => $from->toDateString(),
            'prev_week' => $from->subWeek()->toDateString(),
            'next_week' => $from->addWeek()->toDateString(),
            'today' => now()->startOfDay()->toDateString(),
            'venue_id' => $venueId,
            'venues' => Venue::query()
                ->whereNotNull('active_at')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->all(),
            'days' => $grid['days'],
            'rows' => $grid['rows'],
        ]);
    }

    protected function resolveStart(Request $request): CarbonImmutable
    {
        $raw = $request->string('from')->toString();
        if ($raw === '') {
            return CarbonImmutable::now()->startOfDay();
        }

        try {
            return CarbonImmutable::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::now()->startOfDay();
        }
    }
}
