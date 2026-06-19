<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\VenueStoreRequest;
use App\Http\Requests\VenueUpdateRequest;
use App\Models\Blackout;
use App\Models\Booking;
use App\Models\Space;
use App\Models\Venue;
use App\Models\WorkOrder;
use App\Models\WorkOrderTemplate;
use App\Support\DisplayImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Venue::class);

        $venues = Venue::query()
            ->with('media')
            ->withCount('spaces')
            ->orderByRaw('active_at IS NULL')
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'city' => $venue->address_json['city'] ?? null,
                'state' => $venue->address_json['state'] ?? null,
                'timezone' => $venue->timezone,
                'space_count' => $venue->spaces_count,
                'status' => $venue->isActive() ? 'active' : ($venue->retired_at ? 'retired' : 'coming_soon'),
                'summary' => $venue->settings_json['summary'] ?? null,
                'image_url' => $venue->thumbUrl(),
            ]);

        return Inertia::render('venues/index', [
            'venues' => $venues,
        ]);
    }

    public function archive(): Response
    {
        $this->authorize('viewAny', Venue::class);

        $venues = Venue::onlyTrashed()
            ->withCount('spaces')
            ->orderByDesc('retired_at')
            ->get()
            ->map(fn (Venue $venue) => [
                'id' => $venue->id,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'city' => $venue->address_json['city'] ?? null,
                'state' => $venue->address_json['state'] ?? null,
                'space_count' => $venue->spaces_count,
                'retired_at' => $venue->retired_at?->toIso8601String(),
            ]);

        return Inertia::render('venues/archive', [
            'venues' => $venues,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Venue::class);

        return Inertia::render('venues/create');
    }

    public function store(VenueStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $venue = Venue::query()->create([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'address_json' => $this->addressFrom($data),
            'settings_json' => ['summary' => $data['summary'] ?? null, 'enforce_setup_buffers' => (bool) ($data['enforce_setup_buffers'] ?? false)],
            'active_at' => ($data['is_active'] ?? false) ? now() : null,
        ]);

        DisplayImage::apply($venue, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venue {$venue->name} created."]);

        return to_route('venues.show', $venue);
    }

    public function show(Venue $venue): Response
    {
        $this->authorize('view', $venue);

        $venue->load([
            'media',
            'spaces:id,venue_id,name,kind,sqft,capacity,bookable_unit',
            'spaces.kindRef:id,key,label',
            'spaces.media',
        ]);

        $blackouts = Blackout::query()
            ->where(function ($q) use ($venue) {
                $q->where(function ($qq) use ($venue) {
                    $qq->where('blackoutable_type', Venue::class)
                        ->where('blackoutable_id', $venue->id);
                })->orWhere(function ($qq) use ($venue) {
                    $qq->where('blackoutable_type', Space::class)
                        ->whereIn('blackoutable_id', $venue->spaces->pluck('id'));
                });
            })
            ->where('ends_at', '>=', now()->subDays(30))
            ->orderBy('starts_at')
            ->get();

        $upcoming = Booking::query()
            ->where('venue_id', $venue->id)
            ->where('start_at', '>=', now())
            ->whereIn('status', [
                BookingStatus::Hold->value,
                BookingStatus::Tentative->value,
                BookingStatus::Definite->value,
            ])
            ->with(['client:id,name'])
            ->orderBy('start_at')
            ->limit(50)
            ->get(['id', 'reference', 'name', 'status', 'start_at', 'end_at', 'total_cents', 'client_id']);

        $templates = WorkOrderTemplate::query()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'kind', 'recurrence_rrule', 'lookahead_days']);

        $workOrders = WorkOrder::query()
            ->where('venue_id', $venue->id)
            ->open()
            ->with('assignee:id,name,email')
            ->orderBy('scheduled_for')
            ->limit(25)
            ->get(['id', 'reference', 'title', 'kind', 'status', 'priority', 'scheduled_for', 'assigned_to_user_id', 'venue_id']);

        $stats = [
            'lifetime_bookings' => Booking::query()->where('venue_id', $venue->id)->count(),
            'confirmed_revenue_cents' => (int) Booking::query()
                ->where('venue_id', $venue->id)
                ->whereIn('status', [BookingStatus::Definite->value, BookingStatus::Completed->value])
                ->sum('total_cents'),
            'upcoming_count' => $upcoming->count(),
            'space_count' => $venue->spaces->count(),
            'total_capacity' => (int) $venue->spaces->sum('capacity'),
        ];

        return Inertia::render('venues/show', [
            'venue' => [
                'id' => $venue->id,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'building' => $venue->address_json['building'] ?? null,
                'street' => $venue->address_json['street'] ?? null,
                'city' => $venue->address_json['city'] ?? null,
                'state' => $venue->address_json['state'] ?? null,
                'zip' => $venue->address_json['zip'] ?? null,
                'phone' => $venue->phone,
                'website' => $venue->website,
                'timezone' => $venue->timezone,
                'summary' => $venue->settings_json['summary'] ?? null,
                'enforce_setup_buffers' => $venue->enforcesSetupBuffers(),
                'is_active' => $venue->isActive(),
                'active_at' => $venue->active_at?->toIso8601String(),
                'retired_at' => $venue->retired_at?->toIso8601String(),
                'image_url' => $venue->imageUrl(),
                'spaces' => $venue->spaces->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'kind' => $s->kind,
                    'kind_label' => $s->kindLabel(),
                    'sqft' => $s->sqft,
                    'capacity' => $s->capacity,
                    'bookable_unit' => $s->bookable_unit?->value,
                    'image_url' => $s->thumbUrl(),
                ])->all(),
            ],
            'upcoming_bookings' => $upcoming->map(fn ($b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'name' => $b->name,
                'status' => $b->status?->value,
                'start_at' => $b->start_at?->toIso8601String(),
                'end_at' => $b->end_at?->toIso8601String(),
                'total_cents' => $b->total_cents,
                'client_name' => $b->client?->name,
                'client_id' => $b->client?->id,
            ])->all(),
            'work_order_templates' => $templates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'kind' => $t->kind?->value,
                'recurrence_rrule' => $t->recurrence_rrule,
                'lookahead_days' => $t->lookahead_days,
            ])->all(),
            'work_orders' => $workOrders->map(fn (WorkOrder $w) => [
                'id' => $w->id,
                'reference' => $w->reference,
                'title' => $w->title,
                'kind' => $w->kind?->value,
                'status' => $w->status?->value,
                'priority' => $w->priority,
                'scheduled_for' => $w->scheduled_for?->toIso8601String(),
                'assignee_name' => $w->assignee?->name,
                'is_overdue' => $w->isOverdue(),
            ])->all(),
            'blackouts' => $blackouts->map(fn (Blackout $b) => [
                'id' => $b->id,
                'scope' => $b->blackoutable_type === Venue::class ? 'venue' : 'space',
                'space_id' => $b->blackoutable_type === Space::class ? $b->blackoutable_id : null,
                'space_name' => $b->blackoutable_type === Space::class
                    ? $venue->spaces->firstWhere('id', $b->blackoutable_id)?->name
                    : null,
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
                'reason' => $b->reason,
            ])->all(),
            'stats' => $stats,
        ]);
    }

    public function storeBlackout(Request $request, Venue $venue): RedirectResponse
    {
        $this->authorize('update', $venue);

        $data = $request->validate([
            'space_id' => ['nullable', 'integer', 'exists:spaces,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $target = $venue;
        if (! empty($data['space_id'])) {
            $space = Space::query()->where('venue_id', $venue->id)
                ->where('id', $data['space_id'])
                ->firstOrFail();
            $target = $space;
        }

        $target->blackouts()->create([
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'],
            'created_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blackout added.']);

        return to_route('venues.show', $venue);
    }

    public function destroyBlackout(Venue $venue, Blackout $blackout): RedirectResponse
    {
        $this->authorize('update', $venue);

        $isOnVenue = $blackout->blackoutable_type === Venue::class
            && $blackout->blackoutable_id === $venue->id;
        $isOnSpace = $blackout->blackoutable_type === Space::class
            && Space::query()->where('id', $blackout->blackoutable_id)
                ->where('venue_id', $venue->id)
                ->exists();

        abort_unless($isOnVenue || $isOnSpace, 404);

        $blackout->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Blackout removed.']);

        return to_route('venues.show', $venue);
    }

    public function edit(Venue $venue): Response
    {
        $this->authorize('update', $venue);

        return Inertia::render('venues/edit', [
            'venue' => [
                'id' => $venue->id,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'building' => $venue->address_json['building'] ?? null,
                'street' => $venue->address_json['street'] ?? null,
                'city' => $venue->address_json['city'] ?? null,
                'state' => $venue->address_json['state'] ?? null,
                'zip' => $venue->address_json['zip'] ?? null,
                'phone' => $venue->phone,
                'website' => $venue->website,
                'timezone' => $venue->timezone,
                'summary' => $venue->settings_json['summary'] ?? null,
                'enforce_setup_buffers' => $venue->enforcesSetupBuffers(),
                'exhibitor_handbook_md' => $venue->exhibitor_handbook_md,
                'exhibitor_handbook_published' => $venue->exhibitor_handbook_published_at !== null,
                'is_active' => $venue->isActive(),
                'image_url' => $venue->imageUrl(),
                'has_photo' => $venue->hasUploadedPhoto(),
            ],
        ]);
    }

    public function update(VenueUpdateRequest $request, Venue $venue): RedirectResponse
    {
        $data = $request->validated();

        $settings = $venue->settings_json ?? [];
        $settings['summary'] = $data['summary'] ?? null;
        $settings['enforce_setup_buffers'] = (bool) ($data['enforce_setup_buffers'] ?? false);

        $handbookMd = $data['exhibitor_handbook_md'] ?? null;
        $publishHandbook = (bool) ($data['exhibitor_handbook_published'] ?? false)
            && trim((string) $handbookMd) !== '';

        $venue->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'address_json' => $this->addressFrom($data, $venue->address_json ?? []),
            'settings_json' => $settings,
            'exhibitor_handbook_md' => $handbookMd,
            // stamp on first publish, keep prior stamp, clear when unpublished
            'exhibitor_handbook_published_at' => $publishHandbook
                ? ($venue->exhibitor_handbook_published_at ?? now())
                : null,
            'active_at' => ($data['is_active'] ?? false) ? ($venue->active_at ?? now()) : null,
        ]);

        DisplayImage::apply($venue, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venue {$venue->name} updated."]);

        return to_route('venues.show', $venue);
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        $this->authorize('delete', $venue);

        $venue->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venue {$venue->name} archived."]);

        return to_route('venues.index');
    }

    public function restore(Venue $venue): RedirectResponse
    {
        $this->authorize('restore', $venue);

        $venue->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Venue {$venue->name} restored."]);

        return to_route('venues.show', $venue);
    }

    /**
     * Merge the structured address fields into the existing address_json,
     * preserving any keys the form doesn't manage.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function addressFrom(array $data, array $existing = []): array
    {
        return [
            ...$existing,
            'building' => $data['building'] ?? null,
            'street' => $data['street'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => isset($data['state']) ? strtoupper((string) $data['state']) : null,
            'zip' => $data['zip'] ?? null,
        ];
    }
}
