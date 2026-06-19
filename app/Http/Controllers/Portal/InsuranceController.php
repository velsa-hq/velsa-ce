<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InsuranceCertificateStatus;
use App\Enums\InsurancePolicyType;
use App\Http\Controllers\Controller;
use App\Models\Exhibitor;
use App\Models\InsuranceCertificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $certificates = $exhibitor->insuranceCertificates()
            ->latest('id')
            ->get()
            ->map(fn (InsuranceCertificate $c) => [
                'id' => $c->id,
                'policy_type' => $c->policy_type->label(),
                'carrier' => $c->carrier,
                'expires_on' => $c->expires_on->toDateString(),
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'review_notes' => $c->review_notes,
                'document_url' => $c->documentUrl(),
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('portal/insurance', [
            'certificates' => $certificates,
            'policy_types' => array_map(
                fn (InsurancePolicyType $t) => ['value' => $t->value, 'label' => $t->label()],
                InsurancePolicyType::cases(),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Exhibitor $exhibitor */
        $exhibitor = $request->user('exhibitor');

        $validated = $request->validate([
            'policy_type' => ['required', Rule::enum(InsurancePolicyType::class)],
            'carrier' => ['nullable', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'expires_on' => ['required', 'date'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $certificate = $exhibitor->insuranceCertificates()->create([
            'policy_type' => $validated['policy_type'],
            'carrier' => $validated['carrier'] ?? null,
            'policy_number' => $validated['policy_number'] ?? null,
            'expires_on' => $validated['expires_on'],
            'status' => InsuranceCertificateStatus::Pending,
            'submitted_via_portal' => true,
        ]);

        $certificate->addMediaFromRequest('document')->toMediaCollection('certificate');

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Certificate uploaded - our team will review it shortly.',
        ]);
    }
}
