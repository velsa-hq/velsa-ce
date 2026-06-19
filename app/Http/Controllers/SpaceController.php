<?php

namespace App\Http\Controllers;

use App\Enums\BookableUnit;
use App\Enums\BookingStatus;
use App\Models\Blackout;
use App\Models\BookingSpace;
use App\Models\Space;
use App\Models\SpaceKind;
use App\Models\Venue;
use App\Support\AreaUnit;
use App\Support\DisplayImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * CRUD for the Spaces inside a Venue. Delete soft-deletes via retired_at
 * (Space::DELETED_AT) to preserve history and existing booking references.
 */
class SpaceController extends Controller
{
    public function show(Space $space): Response
    {
        $this->authorize('view', $space);

        $space->load([
            'venue:id,slug,name',
            'kindRef:id,key,label',
            'media',
            'parent:id,name',
            'children:id,parent_space_id,name,kind,capacity',
        ]);

        $upcoming = BookingSpace::query()
            ->where('space_id', $space->id)
            ->where('start_at', '>=', now())
            ->whereHas('booking', fn ($q) => $q->whereIn('status', [
                BookingStatus::Hold->value,
                BookingStatus::Tentative->value,
                BookingStatus::Definite->value,
            ]))
            ->with(['booking:id,reference,name,status,client_id', 'booking.client:id,name'])
            ->orderBy('start_at')
            ->limit(25)
            ->get();

        $blackouts = Blackout::query()
            ->where('blackoutable_type', Space::class)
            ->where('blackoutable_id', $space->id)
            ->where('ends_at', '>=', now()->subDays(30))
            ->orderBy('starts_at')
            ->get();

        return Inertia::render('spaces/show', [
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'kind' => $space->kind,
                'kind_label' => $space->kindLabel(),
                'capacity' => $space->capacity,
                'sqft' => $space->sqft,
                'bookable_unit' => $space->bookable_unit?->value,
                'image_url' => $space->imageUrl(),
                'has_photo' => $space->hasUploadedPhoto(),
                'attributes' => $space->attributes_json ?? [],
                'parent' => $space->parent
                    ? ['id' => $space->parent->id, 'name' => $space->parent->name]
                    : null,
                'children' => $space->children->map(fn (Space $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'capacity' => $c->capacity,
                ])->all(),
            ],
            'venue' => [
                'id' => $space->venue->id,
                'slug' => $space->venue->slug,
                'name' => $space->venue->name,
            ],
            'upcoming_bookings' => $upcoming->map(fn (BookingSpace $bs) => [
                'id' => $bs->booking?->id,
                'reference' => $bs->booking?->reference,
                'name' => $bs->booking?->name,
                'status' => $bs->booking?->status?->value,
                'start_at' => $bs->start_at?->toIso8601String(),
                'end_at' => $bs->end_at?->toIso8601String(),
                'client_name' => $bs->booking?->client?->name,
                'client_id' => $bs->booking?->client?->id,
            ])->all(),
            'blackouts' => $blackouts->map(fn (Blackout $b) => [
                'id' => $b->id,
                'starts_at' => $b->starts_at?->toIso8601String(),
                'ends_at' => $b->ends_at?->toIso8601String(),
                'reason' => $b->reason,
            ])->all(),
            'stats' => [
                'upcoming_count' => $upcoming->count(),
                'sub_space_count' => $space->children->count(),
            ],
        ]);
    }

    public function create(Venue $venue): Response
    {
        $this->authorize('create', Space::class);

        return Inertia::render('spaces/create', [
            'venue' => ['id' => $venue->id, 'slug' => $venue->slug, 'name' => $venue->name],
            'kinds' => $this->kindOptions(),
            'parents' => $this->parentOptions($venue),
            'bookable_units' => $this->bookableUnitOptions(),
        ]);
    }

    public function store(Request $request, Venue $venue): RedirectResponse
    {
        $this->authorize('create', Space::class);

        $data = $this->validatePayload($request, $venue);

        try {
            $space = $venue->spaces()->create($this->modelFields($data));
        } catch (RuntimeException $e) {
            return back()->withErrors(['parent_space_id' => $e->getMessage()])->withInput();
        }

        DisplayImage::apply($space, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Space '{$data['name']}' added."]);

        return to_route('venues.show', $venue);
    }

    public function edit(Space $space): Response
    {
        $this->authorize('update', $space);

        $venue = $space->venue;

        return Inertia::render('spaces/edit', [
            'venue' => ['id' => $venue->id, 'slug' => $venue->slug, 'name' => $venue->name],
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'kind' => $space->kind,
                'capacity' => $space->capacity,
                'sqft' => $space->sqft,
                'bookable_unit' => $space->bookable_unit?->value,
                'parent_space_id' => $space->parent_space_id,
                'image_url' => $space->imageUrl(),
                'has_photo' => $space->hasUploadedPhoto(),
            ],
            'kinds' => $this->kindOptions(),
            'parents' => $this->parentOptions($venue, exclude: $space->id),
            'bookable_units' => $this->bookableUnitOptions(),
        ]);
    }

    public function update(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $venue = $space->venue;
        $data = $this->validatePayload($request, $venue, $space);

        try {
            $space->update($this->modelFields($data));
        } catch (RuntimeException $e) {
            return back()->withErrors(['parent_space_id' => $e->getMessage()])->withInput();
        }

        DisplayImage::apply($space, $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Space '{$space->name}' updated."]);

        return to_route('venues.show', $venue);
    }

    public function uploadFloorPlan(Request $request, Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $request->validate([
            'floorplan' => ['required', 'file', 'image', 'max:10240'],
        ]);

        $space->addMediaFromRequest('floorplan')->toMediaCollection('floorplan');

        return back()->with('toast', ['type' => 'success', 'message' => 'Floor plan uploaded.']);
    }

    public function deleteFloorPlan(Space $space): RedirectResponse
    {
        $this->authorize('update', $space);

        $space->clearMediaCollection('floorplan');

        return back()->with('toast', ['type' => 'success', 'message' => 'Floor plan removed.']);
    }

    public function destroy(Space $space): RedirectResponse
    {
        $this->authorize('delete', $space);

        if ($space->children()->exists()) {
            return back()->withErrors([
                'space' => "Retire or reassign this space's sub-spaces first.",
            ]);
        }

        $venue = $space->venue;
        $name = $space->name;
        $space->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Space '{$name}' retired."]);

        return to_route('venues.show', $venue);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request, Venue $venue, ?Space $space = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::exists('space_kinds', 'key')->where('is_active', true)],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'sqft' => ['nullable', 'integer', 'min:0'],
            'bookable_unit' => ['required', Rule::enum(BookableUnit::class)],
            'parent_space_id' => [
                'nullable',
                'integer',
                $space !== null ? "different:{$space->id}" : 'filled',
                Rule::exists('spaces', 'id')->where('venue_id', $venue->id),
            ],
            ...DisplayImage::rules(),
        ]);
    }

    /**
     * Validated keys that map to Space columns; image inputs are handled
     * separately by DisplayImage::apply().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function modelFields(array $data): array
    {
        $fields = Arr::except($data, ['photo', 'remove_image']);

        // form submits area in the org's display unit; store canonical sqft
        if (isset($fields['sqft']) && $fields['sqft'] !== '') {
            $fields['sqft'] = AreaUnit::toSqft((int) $fields['sqft']);
        }

        return $fields;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function kindOptions(): array
    {
        return SpaceKind::query()
            ->active()
            ->ordered()
            ->get(['key', 'label'])
            ->map(fn (SpaceKind $k) => ['value' => $k->key, 'label' => $k->label])
            ->all();
    }

    /**
     * Candidate parent spaces (all but the one being edited; the model's
     * saving hook still guards against cycles).
     *
     * @return list<array{id: int, name: string}>
     */
    protected function parentOptions(Venue $venue, ?int $exclude = null): array
    {
        return $venue->spaces()
            ->when($exclude !== null, fn ($q) => $q->whereKeyNot($exclude))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Space $s) => ['id' => $s->id, 'name' => $s->name])
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function bookableUnitOptions(): array
    {
        return array_map(
            fn (BookableUnit $u) => [
                'value' => $u->value,
                'label' => ucwords(str_replace('_', ' ', $u->value)),
            ],
            BookableUnit::cases(),
        );
    }
}
