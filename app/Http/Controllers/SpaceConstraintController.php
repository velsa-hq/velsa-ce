<?php

namespace App\Http\Controllers;

use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor for permanent space features (walls, doors, columns, ...) stored
 * as constraints_json. Rendered as a read-only backdrop in the booking diagram.
 */
class SpaceConstraintController extends Controller
{
    public function show(Space $space): Response
    {
        return Inertia::render('admin/spaces/constraints', [
            'venue' => [
                'id' => $space->venue->id,
                'name' => $space->venue->name,
                'slug' => $space->venue->slug,
            ],
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
                'sqft' => $space->sqft,
                'capacity' => $space->capacity,
            ],
            'constraints' => $space->constraints_json ?? [],
            'scale_px_per_foot' => 10,
        ]);
    }

    public function store(Space $space, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'constraints' => ['present', 'array'],
            'constraints.*.id' => ['required', 'string'],
            'constraints.*.kind' => ['required', 'string', 'in:wall,door,window,column,outlet,post'],
            'constraints.*.x' => ['required', 'numeric'],
            'constraints.*.y' => ['required', 'numeric'],
            'constraints.*.width_ft' => ['required', 'numeric', 'min:0.1'],
            'constraints.*.height_ft' => ['required', 'numeric', 'min:0.1'],
            'constraints.*.rotation' => ['nullable', 'numeric'],
            'constraints.*.label' => ['nullable', 'string', 'max:64'],
        ]);

        $space->update([
            'constraints_json' => $data['constraints'],
        ]);

        return back()->with('status', 'Constraints saved.');
    }
}
