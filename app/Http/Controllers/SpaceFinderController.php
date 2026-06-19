<?php

namespace App\Http\Controllers;

use App\Models\SpaceKind;
use App\Models\Venue;
use App\Services\Spaces\SpaceFinder;
use App\Services\Spaces\SpaceFinderCriteria;
use App\Support\AreaUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Best-fit space search over a date window plus optional filters. Renders
 * the form on an empty query so the page doubles as the entry surface.
 */
class SpaceFinderController extends Controller
{
    public function index(Request $request, SpaceFinder $finder): Response
    {
        $hasQuery = $request->filled('starts_at') && $request->filled('ends_at');

        $results = collect();
        $criteriaInput = [];

        if ($hasQuery) {
            $validated = $request->validate([
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date', 'after:starts_at'],
                'attendance' => ['nullable', 'integer', 'min:1'],
                'min_sqft' => ['nullable', 'integer', 'min:0'],
                'kind' => ['nullable', Rule::exists('space_kinds', 'key')->where('is_active', true)],
                'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            ]);

            // min area arrives in the org's display unit; query in canonical
            // sqft but echo the original back so the form repopulates in that unit
            $forQuery = $validated;
            if (isset($forQuery['min_sqft'])) {
                $forQuery['min_sqft'] = AreaUnit::toSqft((int) $forQuery['min_sqft']);
            }

            $criteria = SpaceFinderCriteria::fromArray($forQuery);
            $results = $finder->find($criteria);
            $criteriaInput = $validated;
        }

        return Inertia::render('spaces/find', [
            'criteria' => $criteriaInput,
            'results' => $results->all(),
            'venues' => Venue::query()
                ->whereNotNull('active_at')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->all(),
            'kinds' => SpaceKind::query()
                ->active()
                ->ordered()
                ->get(['key', 'label'])
                ->map(fn (SpaceKind $k) => ['value' => $k->key, 'label' => $k->label])
                ->all(),
        ]);
    }
}
