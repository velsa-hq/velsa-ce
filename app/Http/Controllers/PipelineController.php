<?php

namespace App\Http\Controllers;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\Venue;
use App\Services\Pipeline\PipelineStageConfig;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PipelineController extends Controller
{
    public function index(Request $request, SystemSettings $settings, PipelineStageConfig $stages): Response
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('pipeline.view'), 403);

        $venueId = $request->integer('venue_id') ?: null;

        // grace of 0 = overdue the day after the close date
        $graceDays = (int) $settings->get('defaults.pipeline_overdue_grace_days', 0);
        $overdueCutoff = now()->startOfDay()->subDays($graceDays);

        $leads = Lead::query()
            ->with([
                'client:id,name,type',
                'venue:id,name,slug',
                'owner:id,name,email',
                'convertedBooking:id,reference',
            ])
            ->onBoard()
            ->when($venueId, fn ($q, $v) => $q->where('venue_id', $v))
            // soonest close first, undated last, larger deals break ties
            ->orderByRaw('expected_close_date asc nulls last')
            ->orderByDesc('estimated_value_cents')
            ->get();

        $byStage = [];
        $totals = [];
        foreach (LeadStage::cases() as $stage) {
            $byStage[$stage->value] = [];
            $totals[$stage->value] = [
                'count' => 0,
                'estimated_cents' => 0,
                'weighted_cents' => 0,
            ];
        }

        foreach ($leads as $lead) {
            /** @var LeadStage $stage */
            $stage = $lead->stage;
            $byStage[$stage->value][] = [
                'id' => $lead->id,
                'name' => $lead->name,
                'client_name' => $lead->client?->name,
                'venue_name' => $lead->venue?->name,
                'venue_slug' => $lead->venue?->slug,
                'owner_email' => $lead->owner?->email,
                'estimated_cents' => $lead->estimated_value_cents,
                'probability' => $lead->probability,
                'weighted_cents' => $lead->weightedValueCents(),
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'is_overdue' => ! $stage->isTerminal()
                    && $lead->expected_close_date !== null
                    && Carbon::parse($lead->expected_close_date)->lt($overdueCutoff),
                'lost_reason' => $lead->lost_reason,
                'converted_booking' => $lead->convertedBooking ? [
                    'id' => $lead->convertedBooking->id,
                    'reference' => $lead->convertedBooking->reference,
                ] : null,
            ];

            $totals[$stage->value]['count']++;
            $totals[$stage->value]['estimated_cents'] += $lead->estimated_value_cents;
            $totals[$stage->value]['weighted_cents'] += $lead->weightedValueCents();
        }

        $openWeighted = 0;
        $openCount = 0;
        foreach (LeadStage::cases() as $stage) {
            if ($stage->isOpen()) {
                $openWeighted += $totals[$stage->value]['weighted_cents'];
                $openCount += $totals[$stage->value]['count'];
            }
        }

        return Inertia::render('pipeline/index', [
            'columns' => array_map(fn (LeadStage $s) => [
                'key' => $s->value,
                'label' => $stages->label($s),
                'is_terminal' => $s->isTerminal(),
                'is_lost' => $s === LeadStage::Lost,
                'is_won' => $s === LeadStage::Won,
                'totals' => $totals[$s->value],
                'leads' => $byStage[$s->value],
            ], LeadStage::cases()),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'filters' => ['venue_id' => $venueId],
            'summary' => [
                'open_count' => $openCount,
                'open_weighted_cents' => $openWeighted,
            ],
        ]);
    }

    /**
     * Closed leads that have aged off the active board.
     */
    public function archive(Request $request): Response
    {
        abort_unless((bool) $request->user()?->hasVenuePermission('pipeline.view'), 403);

        $search = trim((string) $request->string('q'));

        $leads = Lead::query()
            ->archived()
            ->with([
                'client:id,name',
                'venue:id,name',
                'convertedBooking:id,reference',
            ])
            ->when($search !== '', function ($q) use ($search): void {
                // lower() not ilike: case-insensitive on both SQLite and Postgres
                $needle = '%'.mb_strtolower($search).'%';
                $q->where(function ($w) use ($needle): void {
                    $w->whereRaw('lower(name) like ?', [$needle])
                        ->orWhereHas('client', fn ($c) => $c->whereRaw('lower(name) like ?', [$needle]));
                });
            })
            ->orderByDesc('closed_at')
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'stage' => $lead->stage?->value,
                'is_won' => $lead->stage === LeadStage::Won,
                'client_name' => $lead->client?->name,
                'venue_name' => $lead->venue?->name,
                'estimated_cents' => $lead->estimated_value_cents,
                'lost_reason' => $lead->lost_reason,
                'closed_at' => $lead->closed_at?->toDateString(),
                'archived_at' => $lead->archived_at?->toDateString(),
                'can_reopen' => $lead->converted_booking_id === null,
                'converted_booking' => $lead->convertedBooking ? [
                    'id' => $lead->convertedBooking->id,
                    'reference' => $lead->convertedBooking->reference,
                ] : null,
            ]);

        return Inertia::render('pipeline/archive', [
            'leads' => $leads,
            'filters' => ['q' => $search],
        ]);
    }
}
