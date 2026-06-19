<?php

use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\Signing\FakeSignatureProvider;
use App\Services\Signing\SignatureProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('binds FakeSignatureProvider as the SignatureProvider in the container', function () {
    expect(app(SignatureProvider::class))->toBeInstanceOf(FakeSignatureProvider::class);
});

it('creates an envelope with a signing URL for each signer', function () {
    $contract = Contract::factory()->create();
    ContractSigner::factory()->count(2)->create(['contract_id' => $contract->id]);

    $envelope = (new FakeSignatureProvider)->createEnvelope($contract->fresh('signers'));

    expect($envelope->envelopeId)->toStartWith('env_')
        ->and($envelope->status)->toBe('sent')
        ->and($envelope->signers)->toHaveCount(2)
        ->and($envelope->signers[0]['signing_url'])->toContain($envelope->envelopeId);
});

it('returns the most-recent envelope status on query', function () {
    $contract = Contract::factory()->create();
    ContractSigner::factory()->create(['contract_id' => $contract->id]);

    $provider = new FakeSignatureProvider;
    $envelope = $provider->createEnvelope($contract->fresh('signers'));

    expect($provider->getEnvelopeStatus($envelope->envelopeId))->toBe('sent');

    $provider->simulateView($envelope->envelopeId);
    expect($provider->getEnvelopeStatus($envelope->envelopeId))->toBe('delivered');

    $provider->simulateSign($envelope->envelopeId);
    expect($provider->getEnvelopeStatus($envelope->envelopeId))->toBe('completed');
});

it('simulating a single sign with multiple signers reports signed_partial', function () {
    $contract = Contract::factory()->create();
    ContractSigner::factory()->count(2)->create(['contract_id' => $contract->id]);

    $provider = new FakeSignatureProvider;
    $envelope = $provider->createEnvelope($contract->fresh('signers'));

    $provider->simulateSign($envelope->envelopeId, signerIndex: 0);

    expect($provider->getEnvelopeStatus($envelope->envelopeId))->toBe('signed_partial');
});
