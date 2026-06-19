<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Enums\TemplateKind;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Venue;
use App\Services\Signing\ContractDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $status = $request->string('status')->toString() ?: null;
        $venueId = $request->integer('venue_id') ?: null;

        $contracts = Contract::query()
            ->with([
                'booking:id,reference,name,venue_id,client_id,start_at',
                'booking.venue:id,name,slug',
                'booking.client:id,name',
                'signers:id,contract_id,name,email,viewed_at,signed_at',
            ])
            ->when($status, fn ($q, $v) => $q->where('status', $v))
            ->when($venueId, fn ($q, $v) => $q->whereHas('booking', fn ($qq) => $qq->where('venue_id', $v)))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $rows = $contracts->getCollection()->map(fn (Contract $c) => [
            'id' => $c->id,
            'reference' => $c->reference,
            'status' => $c->status?->value,
            'kind' => $c->kind,
            'total_cents' => $c->total_cents,
            'sent_at' => $c->sent_at?->toIso8601String(),
            'viewed_at' => $c->viewed_at?->toIso8601String(),
            'signed_at' => $c->signed_at?->toIso8601String(),
            'provider' => $c->provider,
            'provider_envelope_id' => $c->provider_envelope_id,
            'booking' => $c->booking ? [
                'id' => $c->booking->id,
                'reference' => $c->booking->reference,
                'name' => $c->booking->name,
                'start_at' => $c->booking->start_at?->toIso8601String(),
                'venue_name' => $c->booking->venue?->name,
                'client_name' => $c->booking->client?->name,
            ] : null,
            'signers' => $c->signers->map(fn ($s) => [
                'name' => $s->name,
                'email' => $s->email,
                'viewed' => $s->viewed_at !== null,
                'signed' => $s->signed_at !== null,
            ])->all(),
        ]);

        $statusSummary = Contract::query()
            ->when($venueId, fn ($q, $v) => $q->whereHas('booking', fn ($qq) => $qq->where('venue_id', $v)))
            ->selectRaw('status, count(*) as count, sum(total_cents) as total_cents')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return Inertia::render('contracts/index', [
            'contracts' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'total' => $contracts->total(),
                ],
                'links' => [
                    'prev' => $contracts->previousPageUrl(),
                    'next' => $contracts->nextPageUrl(),
                ],
            ],
            'venues' => Venue::query()->active()->orderBy('name')->get(['id', 'name', 'slug']),
            'statuses' => array_map(fn (ContractStatus $s) => $s->value, ContractStatus::cases()),
            'filters' => ['status' => $status, 'venue_id' => $venueId],
            'summary' => $statusSummary,
        ]);
    }

    public function show(Contract $contract): Response
    {
        $this->authorize('view', $contract);

        $contract->load([
            'booking:id,reference,name,client_id,venue_id,start_at,end_at,total_cents,status',
            'booking.client:id,name',
            'booking.venue:id,name,slug',
            'signers',
            'creator:id,name,email',
            'parent:id,reference,kind',
            'addenda:id,parent_contract_id,reference,kind,status,created_at',
        ]);

        return Inertia::render('contracts/show', [
            'contract' => [
                'id' => $contract->id,
                'reference' => $contract->reference,
                'kind' => $contract->kind,
                'status' => $contract->status?->value,
                'total_cents' => $contract->total_cents,
                'rendered_html' => $contract->rendered_html,
                'has_signed_pdf' => $contract->pdf_s3_key !== null,
                'provider' => $contract->provider,
                'provider_envelope_id' => $contract->provider_envelope_id,
                'sent_at' => $contract->sent_at?->toIso8601String(),
                'viewed_at' => $contract->viewed_at?->toIso8601String(),
                'signed_at' => $contract->signed_at?->toIso8601String(),
                'declined_at' => $contract->declined_at?->toIso8601String(),
                'expired_at' => $contract->expired_at?->toIso8601String(),
                'voided_at' => $contract->voided_at?->toIso8601String(),
                'decline_reason' => $contract->decline_reason,
                'created_at' => $contract->created_at?->toIso8601String(),
                'booking' => $contract->booking ? [
                    'id' => $contract->booking->id,
                    'reference' => $contract->booking->reference,
                    'name' => $contract->booking->name,
                    'status' => $contract->booking->status?->value,
                    'start_at' => $contract->booking->start_at?->toIso8601String(),
                    'end_at' => $contract->booking->end_at?->toIso8601String(),
                    'client' => $contract->booking->client ? [
                        'id' => $contract->booking->client->id,
                        'name' => $contract->booking->client->name,
                    ] : null,
                    'venue' => $contract->booking->venue ? [
                        'id' => $contract->booking->venue->id,
                        'name' => $contract->booking->venue->name,
                        'slug' => $contract->booking->venue->slug,
                    ] : null,
                ] : null,
                'signers' => $contract->signers->map(fn ($s) => [
                    'id' => $s->id,
                    'signing_order' => $s->signing_order,
                    'role' => $s->role,
                    'name' => $s->name,
                    'email' => $s->email,
                    'viewed_at' => $s->viewed_at?->toIso8601String(),
                    'signed_at' => $s->signed_at?->toIso8601String(),
                    'declined_at' => $s->declined_at?->toIso8601String(),
                ])->all(),
                'creator' => $contract->creator ? [
                    'id' => $contract->creator->id,
                    'name' => $contract->creator->name,
                    'email' => $contract->creator->email,
                ] : null,
                'parent' => $contract->parent ? [
                    'id' => $contract->parent->id,
                    'reference' => $contract->parent->reference,
                    'kind' => $contract->parent->kind,
                ] : null,
                'addenda' => $contract->addenda->map(fn ($a) => [
                    'id' => $a->id,
                    'reference' => $a->reference,
                    'kind' => $a->kind,
                    'status' => $a->status?->value,
                    'created_at' => $a->created_at?->toIso8601String(),
                ])->all(),
            ],
        ]);
    }

    public function downloadSigned(Contract $contract): StreamedResponse
    {
        $this->authorize('view', $contract);

        abort_if($contract->pdf_s3_key === null, 404, 'No signed document on file.');

        return Storage::download($contract->pdf_s3_key, "{$contract->reference}-signed.pdf");
    }

    /**
     * Rendered HTML wrapped in a Word-compatible envelope served as
     * application/msword; Word edits it natively, no .docx tooling needed.
     */
    public function downloadWord(Contract $contract): HttpResponse
    {
        $this->authorize('view', $contract);

        abort_if($contract->rendered_html === null, 404, 'This contract has no rendered content.');

        $document = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
            .'xmlns:w="urn:schemas-microsoft-com:office:word" '
            .'xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8">'
            .'<title>'.e($contract->reference).'</title></head><body>'
            .$contract->rendered_html
            .'</body></html>';

        return response($document, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="'.$contract->reference.'.doc"',
        ]);
    }

    public function send(Contract $contract, Request $request, ContractDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('send', $contract);

        abort_unless($contract->status === ContractStatus::Draft, 422, 'Contract has already been sent.');

        $signers = $request->validate([
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.role' => ['nullable', 'string', 'max:50'],
            'signers.*.signing_order' => ['nullable', 'integer', 'min:1'],
        ])['signers'];

        try {
            $dispatcher->send($contract, $signers);
        } catch (\RuntimeException $e) {
            // surface DocuSign API/JWT errors as a form error, not a 500
            return back()->withErrors(['send' => 'Could not send via DocuSign: '.$e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Contract {$contract->reference} sent to {$signers[0]['name']}.",
        ]);

        return back();
    }

    public function draftFromBooking(Booking $booking, Request $request, ContractDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $kind = TemplateKind::tryFrom($request->string('kind')->toString() ?: 'contract') ?? TemplateKind::Contract;

        $contract = $dispatcher->draftFromBooking($booking, $kind);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Draft {$kind->value} {$contract->reference} created.",
        ]);

        return to_route('bookings.show', $booking);
    }

    /**
     * Draft an Addendum on a signed parent. A Signed contract is immutable,
     * so any later modification lands as a separate Addendum pointing at the parent.
     */
    public function draftAddendum(Contract $contract, Request $request, ContractDispatcher $dispatcher): RedirectResponse
    {
        $this->authorize('create', Contract::class);

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ])['reason'] ?? null;

        try {
            $addendum = $dispatcher->draftAddendum($contract, $reason);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['addendum' => $e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Addendum {$addendum->reference} drafted against {$contract->reference}.",
        ]);

        return to_route('contracts.show', $addendum);
    }

    /**
     * Force-cancel an in-flight contract. Records the local terminal state;
     * the provider envelope-void is handled by the integration layer.
     */
    public function void(Contract $contract, Request $request): RedirectResponse
    {
        $this->authorize('manage', $contract);

        $reason = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ])['reason'] ?? null;

        if (! $contract->void($reason)) {
            return back()->withErrors(['void' => 'Only a contract awaiting signature can be voided.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Contract {$contract->reference} voided."]);

        return back();
    }

    /**
     * Soft-delete a draft or dead contract. Signed and in-flight are
     * protected; void the latter first.
     */
    public function destroy(Contract $contract): RedirectResponse
    {
        $this->authorize('manage', $contract);

        abort_unless(
            $contract->status?->isDeletable() ?? false,
            422,
            'Only draft or closed contracts can be deleted.',
        );

        $reference = $contract->reference;
        $contract->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Contract {$reference} deleted."]);

        return to_route('contracts.index');
    }

    /** Route binds withTrashed. */
    public function restore(Contract $contract): RedirectResponse
    {
        $this->authorize('manage', $contract);

        $contract->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Contract {$contract->reference} restored."]);

        return to_route('contracts.show', $contract);
    }

    public function archive(Request $request): Response
    {
        $this->authorize('viewAny', Contract::class);

        $search = $request->string('q')->toString() ?: null;

        $contracts = Contract::onlyTrashed()
            ->with([
                'booking:id,reference,name,venue_id,client_id',
                'booking.venue:id,name',
                'booking.client:id,name',
            ])
            ->when($search, fn ($q, $v) => $q->whereRaw('lower(reference) like ?', ['%'.mb_strtolower($v).'%']))
            ->orderByDesc('deleted_at')
            ->paginate(50)
            ->withQueryString();

        $rows = $contracts->getCollection()->map(fn (Contract $c) => [
            'id' => $c->id,
            'reference' => $c->reference,
            'status' => $c->status?->value,
            'kind' => $c->kind,
            'total_cents' => $c->total_cents,
            'deleted_at' => $c->deleted_at?->toDateString(),
            'booking' => $c->booking ? [
                'reference' => $c->booking->reference,
                'name' => $c->booking->name,
                'venue_name' => $c->booking->venue?->name,
                'client_name' => $c->booking->client?->name,
            ] : null,
        ]);

        return Inertia::render('contracts/archive', [
            'contracts' => [
                'data' => $rows,
                'meta' => [
                    'current_page' => $contracts->currentPage(),
                    'last_page' => $contracts->lastPage(),
                    'total' => $contracts->total(),
                ],
                'links' => [
                    'prev' => $contracts->previousPageUrl(),
                    'next' => $contracts->nextPageUrl(),
                ],
            ],
            'filters' => ['q' => $search],
        ]);
    }
}
