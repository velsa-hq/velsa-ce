<?php

namespace App\Services\Signing;

use App\Models\Contract;

/**
 * E-signature provider abstraction. Default binding is the fake driver;
 * a DocuSign-backed driver can be swapped in via a service provider.
 */
interface SignatureProvider
{
    public function createEnvelope(Contract $contract): SignatureEnvelope;

    public function getEnvelopeStatus(string $envelopeId): string;

    /** Returns the raw signed PDF bytes for a completed envelope. */
    public function downloadSignedDocument(string $envelopeId): string;
}
