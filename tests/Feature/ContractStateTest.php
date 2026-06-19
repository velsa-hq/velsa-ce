<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a CT-prefixed reference', function () {
    $contract = Contract::factory()->create(['reference' => null]);

    expect($contract->reference)->toStartWith('CT-');
});

it('uses AD- prefix for addenda', function () {
    $contract = Contract::factory()->create(['reference' => null, 'kind' => 'addendum']);

    expect($contract->reference)->toStartWith('AD-');
});

it('casts status to ContractStatus enum', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create();

    expect($contract->status)->toBe(ContractStatus::Sent);
});

it('markSent transitions Draft -> Sent and records envelope id', function () {
    $contract = Contract::factory()->create(['status' => ContractStatus::Draft->value]);

    expect($contract->markSent('env_test_123'))->toBeTrue()
        ->and($contract->fresh()->status)->toBe(ContractStatus::Sent)
        ->and($contract->fresh()->provider_envelope_id)->toBe('env_test_123')
        ->and($contract->fresh()->sent_at)->not->toBeNull();
});

it('markSent is a no-op for already-sent contracts', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create();

    expect($contract->markSent('env_replacement'))->toBeFalse()
        ->and($contract->fresh()->status)->toBe(ContractStatus::Sent);
});

it('markViewedBy transitions Sent -> Viewed', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create();
    $signer = ContractSigner::factory()->create(['contract_id' => $contract->id]);

    $contract->markViewedBy($signer);

    expect($contract->fresh()->status)->toBe(ContractStatus::Viewed)
        ->and($signer->fresh()->viewed_at)->not->toBeNull();
});

it('markSignedBy transitions to PartiallySigned then Signed', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Viewed)->create();
    $a = ContractSigner::factory()->create(['contract_id' => $contract->id, 'signing_order' => 1]);
    $b = ContractSigner::factory()->create(['contract_id' => $contract->id, 'signing_order' => 2]);

    $contract->markSignedBy($a);
    expect($contract->fresh()->status)->toBe(ContractStatus::PartiallySigned)
        ->and($contract->fresh()->signed_at)->toBeNull();

    $contract->markSignedBy($b);
    expect($contract->fresh()->status)->toBe(ContractStatus::Signed)
        ->and($contract->fresh()->signed_at)->not->toBeNull();
});

it('markDeclined moves the contract to Declined and records reason', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Viewed)->create();
    $signer = ContractSigner::factory()->create(['contract_id' => $contract->id]);

    $contract->markDeclined($signer, 'Date conflict');

    expect($contract->fresh()->status)->toBe(ContractStatus::Declined)
        ->and($contract->fresh()->decline_reason)->toBe('Date conflict');
});

it('refuses to mutate a signed contracts content', function () {
    $contract = Contract::factory()->inStatus(ContractStatus::Signed)->create();

    expect(fn () => $contract->update(['rendered_html' => 'tampered']))
        ->toThrow(RuntimeException::class, 'addendum');
});

it('reports isTerminal and isImmutable correctly per enum', function () {
    expect(ContractStatus::Signed->isImmutable())->toBeTrue()
        ->and(ContractStatus::Sent->isImmutable())->toBeFalse()
        ->and(ContractStatus::Declined->isTerminal())->toBeTrue()
        ->and(ContractStatus::Expired->isTerminal())->toBeTrue()
        ->and(ContractStatus::Draft->isTerminal())->toBeFalse();
});
