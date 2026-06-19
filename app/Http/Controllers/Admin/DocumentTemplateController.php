<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TemplateKind;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for DocumentTemplate (proposal / contract / addendum / invoice /
 * payment_schedule), per venue or global. Deleting a used template is allowed:
 * template_id is nullable on contracts and historical contracts keep their
 * rendered_html snapshot, so dropping a template never rewrites history.
 */
class DocumentTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = DocumentTemplate::query()
            ->with('venue:id,name')
            ->withCount('contracts')
            ->orderBy('kind')
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (DocumentTemplate $t) => $this->row($t))
            ->all();

        return Inertia::render('admin/document-templates/index', [
            'templates' => $templates,
            'kinds' => $this->kindOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/document-templates/create', [
            'kinds' => $this->kindOptions(),
            'venues' => $this->venueOptions(),
            'preselect_kind' => $request->string('kind')->toString() ?: 'contract',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $template = DocumentTemplate::query()->create([
            ...$data,
            'version' => 1,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.document-templates.edit', $template)
            ->with('status', "Template '{$template->name}' created.");
    }

    public function edit(DocumentTemplate $documentTemplate): Response
    {
        $documentTemplate->loadCount('contracts');

        return Inertia::render('admin/document-templates/edit', [
            'template' => array_merge(
                $this->row($documentTemplate),
                ['body_html' => $documentTemplate->body_html],
            ),
            'kinds' => $this->kindOptions(),
            'venues' => $this->venueOptions(),
        ]);
    }

    public function update(DocumentTemplate $documentTemplate, Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        // bump version only when the body changes
        $payload = $data;
        if ($documentTemplate->body_html !== $data['body_html']) {
            $payload['version'] = $documentTemplate->version + 1;
        }

        $documentTemplate->update($payload);

        return back()->with('status', "Template '{$documentTemplate->name}' saved.");
    }

    public function destroy(DocumentTemplate $documentTemplate): RedirectResponse
    {
        $name = $documentTemplate->name;
        $documentTemplate->delete();

        return redirect()
            ->route('admin.document-templates.index')
            ->with('status', "Template '{$name}' deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(DocumentTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'kind' => $t->kind?->value,
            'kind_label' => $t->kind?->label(),
            'version' => $t->version,
            'is_active' => (bool) $t->is_active,
            'venue' => $t->venue ? ['id' => $t->venue->id, 'name' => $t->venue->name] : null,
            'venue_id' => $t->venue_id,
            'contracts_count' => $t->contracts_count ?? 0,
            'updated_at' => $t->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'string', 'in:'.collect(TemplateKind::cases())->pluck('value')->implode(',')],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'name' => ['required', 'string', 'max:200'],
            'body_html' => ['required', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function kindOptions(): array
    {
        return collect(TemplateKind::cases())
            ->map(fn (TemplateKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function venueOptions(): array
    {
        return Venue::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Venue $v) => ['id' => $v->id, 'name' => $v->name])
            ->all();
    }
}
