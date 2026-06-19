<?php

namespace App\Services\Signing;

/**
 * Provider-agnostic representation of an e-signature envelope.
 * Returned by SignatureProvider::createEnvelope() and consumed by the
 * application to update Contract state.
 *
 * @phpstan-type SignerRow array{recipient_id: string, name: string, email: string, signing_url: string}
 */
final class SignatureEnvelope
{
    /**
     * @param  array<int, SignerRow>  $signers
     */
    public function __construct(
        public readonly string $envelopeId,
        public readonly string $status,
        public readonly array $signers,
    ) {}
}
