<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ExhibitorPermitStatus;
use App\Enums\ExhibitorPermitType;
use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\ExhibitorPermit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exhibitor-facing activity-permit requests: list their own requests, raise a
 * new one (optionally with a supporting document), and withdraw one that's
 * still pending review.
 */
class PermitController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $permits = $exhibitor->permits()
            ->latest('id')
            ->get()
            ->map(fn (ExhibitorPermit $p) => [
                'id' => $p->id,
                'permit_type' => $p->permit_type->label(),
                'details' => $p->details,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'review_notes' => $p->review_notes,
                'document_url' => $p->documentUrl(),
                'created_at' => $p->created_at?->toIso8601String(),
            ]);

        return Inertia::render('portal/permits', [
            'permits' => $permits,
            'permit_types' => array_map(
                fn (ExhibitorPermitType $t) => ['value' => $t->value, 'label' => $t->label()],
                ExhibitorPermitType::cases(),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $validated = $request->validate([
            'permit_type' => ['required', Rule::enum(ExhibitorPermitType::class)],
            'details' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $permit = $exhibitor->permits()->create([
            'permit_type' => $validated['permit_type'],
            'details' => $validated['details'],
            'status' => ExhibitorPermitStatus::Pending,
            'submitted_via_portal' => true,
        ]);

        if ($request->hasFile('document')) {
            $permit->addMediaFromRequest('document')->toMediaCollection('document');
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permit request submitted - the venue will review it shortly.',
        ]);
    }

    public function cancel(Request $request, ExhibitorPermit $permit): RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        abort_unless($permit->exhibitor_id === $exhibitor->id, 404);
        abort_unless($permit->isPending(), 422);

        $permit->status = ExhibitorPermitStatus::Cancelled;
        $permit->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permit request withdrawn.',
        ]);
    }
}
