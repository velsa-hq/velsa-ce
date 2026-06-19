<?php

namespace App\Http\Controllers;

use App\Models\Diagram;
use App\Models\LayoutTemplate;
use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD + apply/save-as flows for reusable diagram layouts: browse + delete
 * templates, apply a template (clone its objects into a new DiagramVersion),
 * and save the current diagram objects as a new space-scoped template.
 */
class LayoutTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = LayoutTemplate::query()
            ->with(['space:id,name', 'createdBy:id,name'])
            ->orderBy('space_id')
            ->orderBy('name')
            ->get()
            ->map(fn (LayoutTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'description' => $t->description,
                'space' => $t->space ? ['id' => $t->space->id, 'name' => $t->space->name] : null,
                'object_count' => $t->object_count,
                'seat_count' => $t->seat_count,
                'created_by' => $t->createdBy?->name,
                'updated_at' => $t->updated_at?->toDateTimeString(),
            ])
            ->all();

        return Inertia::render('admin/layout-templates/index', [
            'templates' => $templates,
        ]);
    }

    public function destroy(LayoutTemplate $layoutTemplate): RedirectResponse
    {
        $layoutTemplate->delete();

        return back()->with('status', 'Template deleted.');
    }

    /**
     * Apply a template to a diagram: clone every object into a new
     * DiagramVersion with fresh IDs (so editor drag-state doesn't collide).
     * Honors the diagram lock and the template's space scope.
     */
    public function apply(Diagram $diagram, LayoutTemplate $layoutTemplate, Request $request): RedirectResponse|JsonResponse
    {
        // applying a template mutates the booking's diagram; gate on the
        // booking's edit permission (AC-3)
        $this->authorize('update', $diagram->booking);

        abort_if($diagram->isLocked(), 423, 'Diagram is locked.');
        abort_unless(
            $layoutTemplate->space_id === null || $layoutTemplate->space_id === $diagram->space_id,
            403,
            'This template is scoped to a different space.',
        );

        $mode = $request->string('mode')->toString() ?: 'replace';
        abort_unless(in_array($mode, ['replace', 'append'], true), 422);

        $existing = $mode === 'append'
            ? ($diagram->currentVersion?->objects_json ?? [])
            : [];

        // re-id every object so editor drag state doesn't collide if the same
        // template is applied twice
        $cloned = collect($layoutTemplate->objects_json)
            ->map(function (array $obj) {
                $obj['id'] = 'obj_'.uniqid('', true);

                return $obj;
            })
            ->all();

        $merged = array_merge($existing, $cloned);

        $diagram->saveVersion(
            objects: $merged,
            userId: $request->user()?->id,
            note: "Applied template: {$layoutTemplate->name}",
        );

        // return the merged objects so the editor can re-render; back() alone
        // doesn't sync the local objects array (left Append stale until reload)
        if ($request->wantsJson()) {
            return new JsonResponse([
                'objects' => $merged,
                'mode' => $mode,
                'template' => ['id' => $layoutTemplate->id, 'name' => $layoutTemplate->name],
            ]);
        }

        return back()->with('status', "Applied '{$layoutTemplate->name}'.");
    }

    /**
     * Capture the diagram's current objects as a new template scoped to its
     * space. Coordinates are stored as-is, so re-applying restores positions.
     */
    public function saveAs(Diagram $diagram, Request $request): JsonResponse
    {
        $this->authorize('update', $diagram->booking);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:255'],
            'objects' => ['present', 'array'],
            'objects.*.id' => ['required', 'string'],
            'objects.*.type' => ['required', 'string'],
            'objects.*.x' => ['required', 'numeric'],
            'objects.*.y' => ['required', 'numeric'],
            'objects.*.rotation' => ['nullable', 'numeric'],
            'objects.*.props' => ['nullable', 'array'],
        ]);

        // pull objects from input() not $data so unvalidated nested props
        // (seats, booth_number, colors) survive - validate() drops them
        $objects = $request->input('objects', []);
        $seatCount = collect($objects)
            ->sum(fn (array $o) => (int) ($o['props']['seats'] ?? 0));

        $template = LayoutTemplate::query()->create([
            'space_id' => $diagram->space_id,
            'created_by_user_id' => $request->user()?->id,
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'objects_json' => $objects,
            'object_count' => count($objects),
            'seat_count' => $seatCount,
        ]);

        return response()->json([
            'id' => $template->id,
            'name' => $template->name,
        ]);
    }

    /**
     * Templates the active editor should render in its "Apply" picker
     * for a given space (own + global).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function availableFor(Space $space): array
    {
        return LayoutTemplate::query()
            ->availableTo($space)
            ->orderByRaw('space_id IS NULL DESC')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn (LayoutTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'description' => $t->description,
                'object_count' => $t->object_count,
                'seat_count' => $t->seat_count,
                'is_global' => $t->space_id === null,
            ])
            ->all();
    }
}
