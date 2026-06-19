<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\OutlineItemTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for reusable run-of-show item templates. Too rich for the
 * TaxonomyController kit (duration, department, description, checklist).
 * Templates are independent of items made from them, so no in-use guard;
 * only system rows are protected from deletion.
 */
class OutlineItemTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = OutlineItemTemplate::query()
            ->ordered()
            ->get()
            ->map(fn (OutlineItemTemplate $t) => [
                'id' => $t->id,
                'label' => $t->label,
                'department' => $t->department,
                'default_duration_minutes' => $t->default_duration_minutes,
                'description' => $t->description,
                'checklist' => $t->checklist ?? [],
                'is_active' => $t->is_active,
                'is_system' => $t->is_system,
            ])
            ->all();

        return Inertia::render('admin/outline-item-templates/index', [
            'templates' => $templates,
            'departments' => Department::query()->active()->ordered()->get(['key', 'label'])
                ->map(fn (Department $d) => ['value' => $d->key, 'label' => $d->label])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        OutlineItemTemplate::query()->create([
            ...$data,
            'sort_order' => (int) OutlineItemTemplate::query()->max('sort_order') + 1,
            'is_active' => true,
            'is_system' => false,
        ]);

        return back()->with('status', "Template '{$data['label']}' added.");
    }

    public function update(Request $request, OutlineItemTemplate $outlineItemTemplate): RedirectResponse
    {
        $outlineItemTemplate->update($this->validatePayload($request));

        return back()->with('status', "Template '{$outlineItemTemplate->label}' updated.");
    }

    public function toggle(OutlineItemTemplate $outlineItemTemplate): RedirectResponse
    {
        $outlineItemTemplate->update(['is_active' => ! $outlineItemTemplate->is_active]);

        return back()->with('status', "Template '{$outlineItemTemplate->label}' ".($outlineItemTemplate->is_active ? 'shown' : 'hidden').'.');
    }

    public function destroy(OutlineItemTemplate $outlineItemTemplate): RedirectResponse
    {
        if ($outlineItemTemplate->is_system) {
            return back()->withErrors(['template' => "System templates can't be deleted - deactivate it instead."]);
        }

        $label = $outlineItemTemplate->label;
        $outlineItemTemplate->delete();

        return back()->with('status', "Template '{$label}' deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'department' => ['nullable', Rule::exists('departments', 'key')->where('is_active', true)],
            'default_duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'description' => ['nullable', 'string', 'max:5000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['nullable', 'string', 'max:255'],
        ]);

        // drop blank checklist lines and reindex
        $data['checklist'] = array_values(array_filter(
            $data['checklist'] ?? [],
            fn ($line) => trim((string) $line) !== '',
        ));

        return $data;
    }
}
