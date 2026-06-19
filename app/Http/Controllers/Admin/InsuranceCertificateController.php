<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InsuranceCertificateStatus;
use App\Enums\InsurancePolicyType;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Exhibitor;
use App\Models\InsuranceCertificate;
use App\Services\SystemSettings\SystemSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceCertificateController extends Controller
{
    /** Holder kind => model class, for the polymorphic relation. */
    private const HOLDERS = [
        'client' => Client::class,
        'exhibitor' => Exhibitor::class,
    ];

    public function index(Request $request): Response
    {
        $filter = $request->string('status')->toString() ?: null;
        $reminderDays = (int) app(SystemSettings::class)
            ->get('compliance.expiry_reminder_days', 30);
        $soonThreshold = CarbonImmutable::now()->addDays($reminderDays)->toDateString();

        $certificates = InsuranceCertificate::query()
            ->with(['holder', 'reviewer:id,name,email'])
            ->when($filter === 'expiring', fn ($q) => $q
                ->where('status', InsuranceCertificateStatus::Approved)
                ->whereDate('expires_on', '<=', $soonThreshold))
            ->when($filter && $filter !== 'expiring', fn ($q) => $q->where('status', $filter))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $rows = $certificates->getCollection()->map(fn (InsuranceCertificate $c) => $this->present($c));

        return Inertia::render('admin/insurance-certificates/index', [
            'certificates' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $certificates->currentPage(),
                    'last_page' => $certificates->lastPage(),
                    'per_page' => $certificates->perPage(),
                    'total' => $certificates->total(),
                ],
                'links' => [
                    'prev' => $certificates->previousPageUrl(),
                    'next' => $certificates->nextPageUrl(),
                ],
            ],
            'filters' => ['status' => $filter],
            'counts' => [
                'pending' => InsuranceCertificate::where('status', InsuranceCertificateStatus::Pending)->count(),
                'expiring' => InsuranceCertificate::where('status', InsuranceCertificateStatus::Approved)
                    ->whereDate('expires_on', '<=', $soonThreshold)->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/insurance-certificates/create', [
            'policy_types' => $this->policyTypeOptions(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $c) => ['value' => $c->id, 'label' => $c->name]),
            'exhibitors' => Exhibitor::query()->orderBy('company_name')->get(['id', 'company_name'])
                ->map(fn (Exhibitor $e) => ['value' => $e->id, 'label' => $e->company_name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'holder_kind' => ['required', Rule::in(array_keys(self::HOLDERS))],
            'holder_id' => ['required', 'integer'],
            'policy_type' => ['required', Rule::enum(InsurancePolicyType::class)],
            'carrier' => ['nullable', 'string', 'max:150'],
            'policy_number' => ['nullable', 'string', 'max:100'],
            'coverage_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'effective_date' => ['nullable', 'date'],
            'expires_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $holderType = self::HOLDERS[$validated['holder_kind']];
        abort_unless($holderType::query()->whereKey($validated['holder_id'])->exists(), 422);

        $certificate = InsuranceCertificate::create([
            'holder_type' => $holderType,
            'holder_id' => $validated['holder_id'],
            'policy_type' => $validated['policy_type'],
            'carrier' => $validated['carrier'] ?? null,
            'policy_number' => $validated['policy_number'] ?? null,
            'coverage_amount_cents' => isset($validated['coverage_amount'])
                ? (int) round(((float) $validated['coverage_amount']) * 100)
                : null,
            'effective_date' => $validated['effective_date'] ?? null,
            'expires_on' => $validated['expires_on'],
            'status' => InsuranceCertificateStatus::Pending,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        $certificate->addMediaFromRequest('document')->toMediaCollection('certificate');

        return to_route('admin.insurance-certificates.index')
            ->with('toast', ['type' => 'success', 'message' => 'Certificate recorded and pending review.']);
    }

    public function update(Request $request, InsuranceCertificate $insuranceCertificate): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                InsuranceCertificateStatus::Approved->value,
                InsuranceCertificateStatus::Rejected->value,
            ])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = InsuranceCertificateStatus::from($validated['status']);

        $insuranceCertificate->status = $status;
        $insuranceCertificate->review_notes = $validated['review_notes'] ?? null;
        $insuranceCertificate->reviewed_by = $request->user()?->id;
        $insuranceCertificate->reviewed_at = now();
        $insuranceCertificate->save();

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Certificate {$status->label()}.",
        ]);
    }

    public function destroy(InsuranceCertificate $insuranceCertificate): RedirectResponse
    {
        $insuranceCertificate->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Certificate deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InsuranceCertificate $c): array
    {
        $holder = $c->holder;
        $holderName = match (true) {
            $holder instanceof Client => $holder->name,
            $holder instanceof Exhibitor => $holder->company_name,
            default => 'Unknown',
        };

        return [
            'id' => $c->id,
            'holder_kind' => $holder instanceof Exhibitor ? 'Exhibitor' : 'Client',
            'holder_name' => $holderName,
            'policy_type' => $c->policy_type->label(),
            'carrier' => $c->carrier,
            'policy_number' => $c->policy_number,
            'coverage_amount_cents' => $c->coverage_amount_cents,
            'effective_date' => $c->effective_date?->toDateString(),
            'expires_on' => $c->expires_on->toDateString(),
            'status' => $c->status->value,
            'status_label' => $c->status->label(),
            'review_notes' => $c->review_notes,
            'submitted_via_portal' => $c->submitted_via_portal,
            'document_url' => $c->documentUrl(),
            'reviewer' => $c->reviewer ? ['name' => $c->reviewer->name] : null,
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    private function policyTypeOptions(): array
    {
        return array_map(
            fn (InsurancePolicyType $t) => ['value' => $t->value, 'label' => $t->label()],
            InsurancePolicyType::cases(),
        );
    }
}
