<?php

namespace App\Services\Signing;

use App\Models\Contract;
use Illuminate\Support\Str;

/**
 * In-memory signing provider so tests can simulate the envelope lifecycle
 * without hitting an external API.
 */
class FakeSignatureProvider implements SignatureProvider
{
    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $envelopes = [];

    public function createEnvelope(Contract $contract): SignatureEnvelope
    {
        $envelopeId = 'env_'.Str::uuid()->toString();

        $signers = $contract->signers()->get()->map(fn ($signer) => [
            'recipient_id' => 'r_'.$signer->id,
            'name' => $signer->name,
            'email' => $signer->email,
            'signing_url' => "https://docusign.test/sign/{$envelopeId}/{$signer->id}",
        ])->all();

        $this->envelopes[$envelopeId] = [
            'contract_id' => $contract->id,
            'status' => 'sent',
            'signers' => $signers,
            'created_at' => now()->toIso8601String(),
        ];

        return new SignatureEnvelope(
            envelopeId: $envelopeId,
            status: 'sent',
            signers: $signers,
        );
    }

    public function getEnvelopeStatus(string $envelopeId): string
    {
        return $this->envelopes[$envelopeId]['status'] ?? 'unknown';
    }

    public function downloadSignedDocument(string $envelopeId): string
    {
        // minimal valid PDF stand-in so the storage flow runs without a real provider
        return "%PDF-1.4\n% Fake executed contract for {$envelopeId}\n%%EOF\n";
    }

    /**
     * Test helper: simulate a signer viewing the envelope. Returns the recipient id.
     */
    public function simulateView(string $envelopeId, int $signerIndex = 0): string
    {
        $envelope = &$this->envelopes[$envelopeId];
        $envelope['status'] = 'delivered';

        return $envelope['signers'][$signerIndex]['recipient_id'];
    }

    /**
     * Test helper: simulate a signer signing. Returns the recipient id.
     */
    public function simulateSign(string $envelopeId, int $signerIndex = 0): string
    {
        $envelope = &$this->envelopes[$envelopeId];
        $envelope['signers'][$signerIndex]['signed_at'] = now()->toIso8601String();

        $allSigned = collect($envelope['signers'])->every(fn ($s) => isset($s['signed_at']));
        $envelope['status'] = $allSigned ? 'completed' : 'signed_partial';

        return $envelope['signers'][$signerIndex]['recipient_id'];
    }
}
