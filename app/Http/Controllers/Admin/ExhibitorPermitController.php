<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExhibitorPermitStatus;
use App\Http\Controllers\Controller;
use App\Models\ExhibitorPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff review queue for exhibitor activity permits.
 * Authz: compliance.view to read, compliance.manage to decide.
 */
class ExhibitorPermitController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('status')->toString() ?: null;

        $permits = ExhibitorPermit::query()
            ->with(['exhibitor:id,company_name', 'reviewer:id,name'])
            ->when($filter, fn ($q) => $q->where('status', $filter))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $rows = $permits->getCollection()->map(fn (ExhibitorPermit $p) => $this->present($p));

        return Inertia::render('admin/exhibitor-permits/index', [
            'permits' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $permits->currentPage(),
                    'last_page' => $permits->lastPage(),
                    'per_page' => $permits->perPage(),
                    'total' => $permits->total(),
                ],
                'links' => [
                    'prev' => $permits->previousPageUrl(),
                    'next' => $permits->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $filter],
            'counts' => [
                'pending' => ExhibitorPermit::where('status', ExhibitorPermitStatus::Pending)->count(),
            ],
        ]);
    }

    public function update(Request $request, ExhibitorPermit $exhibitorPermit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ExhibitorPermitStatus::Approved->value,
                ExhibitorPermitStatus::Denied->value,
            ])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = ExhibitorPermitStatus::from($validated['status']);

        $exhibitorPermit->status = $status;
        $exhibitorPermit->review_notes = $validated['review_notes'] ?? null;
        $exhibitorPermit->reviewed_by = $request->user()?->id;
        $exhibitorPermit->reviewed_at = now();
        $exhibitorPermit->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Permit {$status->label()}.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ExhibitorPermit $p): array
    {
        return [
            'id' => $p->id,
            'exhibitor_name' => $p->exhibitor->company_name,
            'permit_type' => $p->permit_type->label(),
            'details' => $p->details,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'review_notes' => $p->review_notes,
            'submitted_via_portal' => $p->submitted_via_portal,
            'document_url' => $p->documentUrl(),
            'reviewer' => $p->reviewer ? ['name' => $p->reviewer->name] : null,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
