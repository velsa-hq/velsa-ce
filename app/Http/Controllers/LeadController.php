<?php

namespace App\Http\Controllers;

use App\Enums\ActivityKind;
use App\Enums\LeadStage;
use App\Http\Requests\LeadStoreRequest;
use App\Http\Requests\LeadUpdateRequest;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Venue;
use App\Services\Accounting\ValueFormatter;
use App\Services\Pipeline\PipelineStageConfig;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function create(PipelineStageConfig $stages): Response
    {
        $this->authorize('create', Lead::class);

        return Inertia::render('leads/create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])->values(),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->values(),
            // new opportunities only enter the funnel at an open stage
            'stages' => array_values(array_filter(
                array_map(fn (LeadStage $s) => $s->value, LeadStage::cases()),
                fn (string $value) => LeadStage::from($value)->isOpen(),
            )),
            'stage_labels' => $this->stageLabels($stages),
        ]);
    }

    public function store(LeadStoreRequest $request, PipelineStageConfig $stages): RedirectResponse
    {
        $data = $request->validated();
        $cents = isset($data['estimated_value_dollars'])
            ? Money::toCents($data['estimated_value_dollars'])
            : 0;
        $stage = LeadStage::from($data['stage']);

        $lead = Lead::create([
            'client_id' => $data['client_id'],
            'venue_id' => $data['venue_id'] ?? null,
            'owner_user_id' => $request->user()->id,
            'name' => $data['name'],
            'stage' => $stage->value,
            'estimated_value_cents' => $cents,
            // probability tracks the stage default at creation; tuned later
            // from the edit form
            'probability' => $stages->probability($stage),
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source' => $data['source'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Opportunity {$lead->name} created."]);

        return to_route('leads.show', $lead);
    }

    public function show(Lead $lead, PipelineStageConfig $stages): Response
    {
        $this->authorize('view', $lead);

        $lead->load([
            'client:id,name,type',
            'venue:id,name,slug',
            'owner:id,name,email',
            'convertedBooking:id,reference',
        ]);

        $activities = $lead->activities()
            ->with('user:id,name,email')
            ->orderByDesc('due_at')
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'kind' => $a->kind?->value,
                'summary' => $a->summary,
                'note' => $a->note,
                'due_at' => $a->due_at?->toIso8601String(),
                'completed_at' => $a->completed_at?->toIso8601String(),
                'is_overdue' => $a->isOverdue(),
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'email' => $a->user->email,
                ] : null,
            ]);

        return Inertia::render('leads/show', [
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'stage' => $lead->stage?->value,
                'estimated_value_cents' => $lead->estimated_value_cents,
                'probability' => $lead->probability,
                'weighted_value_cents' => $lead->weightedValueCents(),
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'source' => $lead->source,
                'lost_reason' => $lead->lost_reason,
                'notes' => $lead->notes,
                'closed_at' => $lead->closed_at?->toIso8601String(),
                'converted_at' => $lead->converted_at?->toIso8601String(),
                'client' => $lead->client ? [
                    'id' => $lead->client->id,
                    'name' => $lead->client->name,
                    'type' => $lead->client->type?->value,
                ] : null,
                'venue' => $lead->venue ? [
                    'id' => $lead->venue->id,
                    'name' => $lead->venue->name,
                    'slug' => $lead->venue->slug,
                ] : null,
                'owner' => $lead->owner ? [
                    'id' => $lead->owner->id,
                    'name' => $lead->owner->name,
                    'email' => $lead->owner->email,
                ] : null,
                'converted_booking' => $lead->convertedBooking ? [
                    'id' => $lead->convertedBooking->id,
                    'reference' => $lead->convertedBooking->reference,
                ] : null,
            ],
            'activities' => $activities,
            'activity_kinds' => array_map(fn (ActivityKind $k) => $k->value, ActivityKind::cases()),
            'stage_labels' => $this->stageLabels($stages),
        ]);
    }

    public function edit(Lead $lead, PipelineStageConfig $stages): Response
    {
        $this->authorize('update', $lead);

        $lead->load(['client:id,name', 'venue:id,name']);

        return Inertia::render('leads/edit', [
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'stage' => $lead->stage?->value,
                'client_id' => $lead->client_id,
                'venue_id' => $lead->venue_id,
                'estimated_value_dollars' => $lead->estimated_value_cents > 0
                    ? ValueFormatter::apply($lead->estimated_value_cents, 'money:dot')
                    : null,
                'probability' => $lead->probability,
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'source' => $lead->source,
                'lost_reason' => $lead->lost_reason,
                'notes' => $lead->notes,
            ],
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])->values(),
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name'])->values(),
            'stages' => array_map(fn (LeadStage $s) => $s->value, LeadStage::cases()),
            'stage_labels' => $this->stageLabels($stages),
        ]);
    }

    public function update(LeadUpdateRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        $cents = isset($data['estimated_value_dollars'])
            ? Money::toCents($data['estimated_value_dollars'])
            : 0;

        $lead->update([
            'client_id' => $data['client_id'],
            'venue_id' => $data['venue_id'] ?? null,
            'name' => $data['name'],
            'stage' => $data['stage'],
            'estimated_value_cents' => $cents,
            'probability' => $data['probability'],
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'source' => $data['source'] ?? null,
            'lost_reason' => $data['lost_reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lead {$lead->name} updated."]);

        return to_route('leads.show', $lead);
    }

    /**
     * Move a lead to a new stage in one shot - the drag-and-drop endpoint
     * behind the pipeline Kanban. Validates the stage and (for Lost) requires
     * a reason; the rest is handled by the Lead model's saving hook.
     */
    public function updateStage(Request $request, Lead $lead, PipelineStageConfig $stageConfig): RedirectResponse
    {
        $this->authorize('update', $lead);

        $stages = array_map(fn (LeadStage $s) => $s->value, LeadStage::cases());

        $data = $request->validate([
            'stage' => ['required', Rule::in($stages)],
            'lost_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newStage = LeadStage::from($data['stage']);

        // Lost is the only stage that requires a reason; keep parity with the
        // edit form so the drag path can't sneak in a reasonless Lost
        if ($newStage === LeadStage::Lost) {
            $reason = trim((string) ($data['lost_reason'] ?? ''));
            if ($reason === '') {
                return back()->withErrors([
                    'lost_reason' => 'A reason is required when marking a lead as Lost.',
                ]);
            }
            $lead->lost_reason = $reason;
        } else {
            // leaving Lost clears the stale reason so audits don't misrepresent
            // the current state
            $lead->lost_reason = null;
        }

        // reset probability to the stage default; drag-drop is for funnel
        // movement, not fine-tuning (tune from the edit form)
        $lead->probability = $stageConfig->probability($newStage);
        $lead->stage = $newStage->value;
        $lead->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Moved {$lead->name} to ".str_replace('_', ' ', $newStage->value).'.',
        ]);

        return back();
    }

    /**
     * Spin up a fresh opportunity from an existing one - handy for recurring
     * clients or re-pursuing a Lost deal without disturbing its record. The
     * copy starts clean at New; the user lands on its edit form.
     */
    public function clone(Request $request, Lead $lead, PipelineStageConfig $stages): RedirectResponse
    {
        $this->authorize('update', $lead);

        $copy = Lead::create([
            'client_id' => $lead->client_id,
            'venue_id' => $lead->venue_id,
            'owner_user_id' => $request->user()->id,
            'name' => $lead->name.' (copy)',
            'stage' => LeadStage::New->value,
            'estimated_value_cents' => $lead->estimated_value_cents,
            'probability' => $stages->probability(LeadStage::New),
            // a clone is a fresh pursuit - drop the prior timeline
            'expected_close_date' => null,
            'source' => $lead->source,
            'notes' => $lead->notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opportunity cloned - adjust the details below.']);

        return to_route('leads.edit', $copy);
    }

    /**
     * Reinsert a closed opportunity into the active funnel at Qualified. Clears
     * the Lost reason and un-archives it. Converted Won deals can't be reopened
     * (they're real bookings now) - clone instead.
     */
    public function reopen(Lead $lead, PipelineStageConfig $stages): RedirectResponse
    {
        $this->authorize('update', $lead);

        if (! $this->isClosed($lead)) {
            return back()->withErrors(['stage' => 'Only closed opportunities can be reopened.']);
        }

        if ($lead->converted_booking_id !== null) {
            return back()->withErrors(['stage' => 'This opportunity already converted to a booking; clone it instead.']);
        }

        $stage = LeadStage::Qualified;
        $lead->update([
            'stage' => $stage->value,
            'probability' => $stages->probability($stage),
            'lost_reason' => null,
            'archived_at' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Reopened {$lead->name} into the pipeline."]);

        return to_route('leads.show', $lead);
    }

    /**
     * Manually archive a closed opportunity ahead of the scheduled window.
     * Open leads must be closed first.
     */
    public function archive(Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        if (! $this->isClosed($lead)) {
            return back()->withErrors(['archive' => 'Only closed opportunities can be archived. Mark it Won or Lost first.']);
        }

        $lead->update(['archived_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Archived {$lead->name}."]);

        return to_route('pipeline.index');
    }

    /**
     * Whether a lead sits at a terminal (Won/Lost) stage. Reads the raw
     * persisted stage so it holds regardless of enum-cast resolution.
     */
    protected function isClosed(Lead $lead): bool
    {
        return in_array(
            $lead->getRawOriginal('stage'),
            [LeadStage::Won->value, LeadStage::Lost->value],
            true,
        );
    }

    /**
     * Stage value -> effective display label map for the client.
     *
     * @return array<string, string>
     */
    protected function stageLabels(PipelineStageConfig $stages): array
    {
        $map = [];
        foreach ($stages->all() as $stage) {
            $map[$stage['value']] = $stage['label'];
        }

        return $map;
    }

    public function storeActivity(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);

        $kinds = array_map(fn (ActivityKind $k) => $k->value, ActivityKind::cases());

        $data = $request->validate([
            'kind' => ['required', Rule::in($kinds)],
            'summary' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $lead->activities()->create([
            'user_id' => $request->user()->id,
            'kind' => $data['kind'],
            'summary' => $data['summary'],
            'note' => $data['note'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Activity added.']);

        return to_route('leads.show', $lead);
    }

    public function toggleActivity(Lead $lead, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $lead);

        abort_unless(
            $activity->subject_type === Lead::class && $activity->subject_id === $lead->id,
            404,
        );

        $activity->update([
            'completed_at' => $activity->completed_at === null ? now() : null,
        ]);

        return back();
    }
}
