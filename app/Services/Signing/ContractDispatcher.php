<?php

namespace App\Services\Signing;

use App\Enums\ContractStatus;
use App\Enums\TemplateKind;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Models\DocumentTemplate;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Send-contract workflow: render template, create signer rows, hand off to
 * the SignatureProvider, persist the envelope id, advance status, audit.
 */
class ContractDispatcher
{
    public function __construct(
        protected SignatureProvider $provider,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Render the contract from its booking + template and send it.
     *
     * @param  array<int, array{name: string, email: string, role?: string, signing_order?: int}>  $signers
     */
    public function send(Contract $contract, array $signers): SignatureEnvelope
    {
        // signers must exist before envelope creation so the provider can map them
        foreach ($signers as $idx => $row) {
            ContractSigner::query()->updateOrCreate(
                ['contract_id' => $contract->id, 'email' => $row['email']],
                [
                    'name' => $row['name'],
                    'role' => $row['role'] ?? 'client',
                    'signing_order' => $row['signing_order'] ?? $idx + 1,
                ],
            );
        }

        // render the body only if a template is attached and nothing is rendered yet
        if ($contract->template !== null && $contract->rendered_html === null) {
            $contract->update([
                'rendered_html' => $this->renderForBooking($contract, $contract->template),
            ]);
        }

        $envelope = $this->provider->createEnvelope($contract->fresh('signers'));

        // persist provider recipient ids back onto the signer rows
        foreach ($envelope->signers as $row) {
            ContractSigner::query()
                ->where('contract_id', $contract->id)
                ->where('email', $row['email'])
                ->update(['provider_recipient_id' => $row['recipient_id']]);
        }

        $contract->markSent($envelope->envelopeId);

        $this->auditLogger->record(
            eventType: 'contract.sent',
            subject: $contract->fresh(),
            payload: [
                'envelope_id' => $envelope->envelopeId,
                'signer_count' => count($envelope->signers),
                'provider' => $contract->provider,
            ],
        );

        return $envelope;
    }

    /**
     * Create a draft Contract from a booking + its venue's active template.
     * Does not send.
     */
    public function draftFromBooking(Booking $booking, TemplateKind $kind = TemplateKind::Contract): Contract
    {
        $template = DocumentTemplate::query()
            ->forVenueKind($booking->venue_id, $kind)
            ->first();

        return Contract::query()->create([
            'booking_id' => $booking->id,
            'template_id' => $template?->id,
            'kind' => $kind->value,
            'status' => ContractStatus::Draft->value,
            'total_cents' => $booking->total_cents,
            'rendered_html' => $template ? $this->renderForBookingTemplate($booking, $template) : null,
        ]);
    }

    /**
     * Create a draft Addendum on a signed parent contract. Signed contracts
     * are immutable; any change lands as a new Addendum referencing the parent
     * and carries its own lifecycle, never mutating the parent. Throws if the
     * parent is not in a signed state.
     */
    public function draftAddendum(Contract $parent, ?string $reason = null): Contract
    {
        $status = $parent->status instanceof ContractStatus
            ? $parent->status
            : ($parent->status !== null ? ContractStatus::from((string) $parent->status) : null);

        if ($status !== ContractStatus::Signed && $status !== ContractStatus::PartiallySigned) {
            throw new \RuntimeException(
                'Addenda may only be drafted on a signed parent contract.',
            );
        }

        $booking = $parent->booking;
        $template = $booking !== null
            ? DocumentTemplate::query()->forVenueKind($booking->venue_id, TemplateKind::Addendum)->first()
            : null;

        $body = $template !== null && $booking !== null
            ? $this->renderForBookingTemplate($booking, $template)
            : null;

        if ($body === null && $reason !== null) {
            // fallback body when no addendum template is on file
            $body = "<h1>Addendum to {$parent->reference}</h1>"
                .'<p><strong>Reason:</strong> '.e($reason).'</p>'
                .'<p>This addendum supersedes the affected sections of the parent contract.</p>';
        }

        $addendum = Contract::query()->create([
            'booking_id' => $parent->booking_id,
            'template_id' => $template?->id,
            'parent_contract_id' => $parent->id,
            'kind' => 'addendum',
            'status' => ContractStatus::Draft->value,
            'total_cents' => 0,
            'rendered_html' => $body,
        ]);

        $this->auditLogger->record(
            eventType: 'contract.addendum_drafted',
            subject: $addendum,
            payload: [
                'parent_contract_id' => $parent->id,
                'parent_reference' => $parent->reference,
                'reason' => $reason,
            ],
        );

        return $addendum;
    }

    /**
     * Fetch and store the executed signed PDF. Idempotent: no-op if there's no
     * envelope or the document is already stored. Returns the storage key, or
     * null when there's nothing to fetch.
     */
    public function storeSignedDocument(Contract $contract): ?string
    {
        if ($contract->provider_envelope_id === null) {
            return null;
        }
        if ($contract->pdf_s3_key !== null) {
            return $contract->pdf_s3_key;
        }

        $bytes = $this->provider->downloadSignedDocument($contract->provider_envelope_id);
        $key = "contracts/{$contract->id}/{$contract->reference}-signed.pdf";
        Storage::put($key, $bytes);
        $contract->update(['pdf_s3_key' => $key]);

        $this->auditLogger->record(
            eventType: 'contract.signed_document_stored',
            subject: $contract,
            payload: ['key' => $key, 'envelope_id' => $contract->provider_envelope_id],
        );

        return $key;
    }

    protected function renderForBooking(Contract $contract, DocumentTemplate $template): string
    {
        return $this->renderForBookingTemplate($contract->booking, $template);
    }

    protected function renderForBookingTemplate(Booking $booking, DocumentTemplate $template): string
    {
        return $template->render([
            'booking' => [
                'reference' => $booking->reference,
                'name' => $booking->name,
                'start_date' => $booking->start_at?->toFormattedDateString(),
                'end_date' => $booking->end_at?->toFormattedDateString(),
                'total' => '$'.number_format($booking->total_cents / 100, 2),
            ],
            'venue' => [
                'name' => $booking->venue?->name,
            ],
            'client' => [
                'name' => $booking->client?->name,
            ],
            // comma-joined booked spaces for the {{spaces}} template token
            'spaces' => $booking->spaces
                ->map(fn ($bookingSpace) => $bookingSpace->space?->name)
                ->filter()
                ->implode(', '),
        ]);
    }
}
