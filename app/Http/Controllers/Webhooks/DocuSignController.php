<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\ContractStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\AuditLogger;
use App\Services\Signing\ContractDispatcher;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives DocuSign Connect notifications (JSON SIM) and walks the parent
 * Contract through its lifecycle. Binds on `provider_envelope_id` to find the
 * Contract, then routes each event to the matching mark*() helper.
 *
 * No CSRF: this is a server-to-server callback (the webhooks/* group is
 * excluded in bootstrap/app.php).
 */
class DocuSignController extends Controller
{
    public function handle(Request $request, AuditLogger $auditLogger, SystemSettings $settings, ContractDispatcher $dispatcher): JsonResponse
    {
        if (! $this->signatureIsValid($request, $settings)) {
            Log::warning('docusign.webhook.invalid_signature');

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $event = $payload['event'] ?? null;
        $envelopeId = $payload['data']['envelopeId'] ?? null;

        if (! is_string($event) || ! is_string($envelopeId)) {
            return response()->json(['error' => 'invalid payload'], 422);
        }

        $contract = Contract::query()
            ->where('provider_envelope_id', $envelopeId)
            ->first();

        if ($contract === null) {
            // unknown envelope - ack 200 so DocuSign doesn't retry, but log it
            // (common cause: an envelope from another environment hitting this URL)
            Log::info('docusign.webhook.unknown_envelope', [
                'event' => $event,
                'envelope_id' => $envelopeId,
            ]);

            return response()->json(['status' => 'ok', 'note' => 'unknown envelope']);
        }

        $signer = isset($payload['data']['recipientEmail'])
            ? ContractSigner::query()
                ->where('contract_id', $contract->id)
                ->where('email', $payload['data']['recipientEmail'])
                ->first()
            : null;

        match ($event) {
            'recipient-sent' => null, // already Sent from createEnvelope
            'recipient-delivered',
            'recipient-viewed' => $signer ? $contract->markViewedBy($signer) : null,
            'recipient-completed' => $signer ? $contract->markSignedBy($signer) : null,
            'recipient-declined' => $signer
                ? $contract->markDeclined($signer, $payload['data']['declinedReason'] ?? null)
                : null,
            // envelope-level events are authoritative but must not resurrect a
            // contract already in a terminal state (e.g. late envelope-completed
            // after a decline)
            'envelope-completed' => $contract->status?->isTerminal() ? null : $contract->update([
                'status' => ContractStatus::Signed->value,
                'signed_at' => now(),
            ]),
            'envelope-declined' => $contract->status?->isTerminal() ? null : $contract->update([
                'status' => ContractStatus::Declined->value,
                'declined_at' => now(),
                'decline_reason' => $payload['data']['declinedReason'] ?? null,
            ]),
            'envelope-voided' => $contract->status?->isTerminal() ? null : $contract->update([
                'status' => ContractStatus::Voided->value,
                'voided_at' => now(),
            ]),
            default => null,
        };

        // once fully signed, capture the executed PDF (the contract of record);
        // a failure doesn't fail the webhook - the reconciliation job backstops
        $contract->refresh();
        if ($contract->status === ContractStatus::Signed && $contract->pdf_s3_key === null) {
            try {
                $dispatcher->storeSignedDocument($contract);
            } catch (\Throwable $e) {
                Log::warning('docusign.webhook.signed_pdf_store_failed', [
                    'envelope_id' => $envelopeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $auditLogger->record(
            eventType: 'contract.docusign_webhook',
            subject: $contract->fresh(),
            payload: [
                'event' => $event,
                'envelope_id' => $envelopeId,
                'recipient_email' => $payload['data']['recipientEmail'] ?? null,
                'status_after' => $contract->fresh()->status?->value,
            ],
        );

        return response()->json(['status' => 'ok']);
    }

    /**
     * Verify the DocuSign Connect HMAC signature. When DocuSign is live but no
     * key is set, REJECT (fail closed) since the webhook mutates contract
     * status; the no-key allow path is only for the Fake driver / un-enabled
     * environments. With a key set, the request must carry a matching
     * X-DocuSign-Signature-* header (base64(HMAC-SHA256(body, key))); DocuSign
     * sends several for rotation, so any constant-time match passes.
     */
    protected function signatureIsValid(Request $request, SystemSettings $settings): bool
    {
        $key = (string) $settings->get('integrations.docusign.connect_hmac_key', '');

        if ($key === '') {
            // fail closed when DocuSign is live: an unsigned webhook could forge
            // "envelope-completed" -> Signed, so a missing HMAC key must reject
            // (NIST SI-10 / SC-8); the no-key bypass is only for the Fake driver
            if ((bool) $settings->get('integrations.docusign.enabled')) {
                Log::error('docusign.webhook.rejected: Connect HMAC key not set while DocuSign is enabled - set integrations.docusign.connect_hmac_key.');

                return false;
            }

            return true;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $key, true));

        foreach ($request->headers->all() as $name => $values) {
            if (! str_starts_with($name, 'x-docusign-signature-')) {
                continue;
            }
            foreach ($values as $value) {
                if (is_string($value) && hash_equals($expected, $value)) {
                    return true;
                }
            }
        }

        return false;
    }
}
