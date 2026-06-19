<?php

use App\Enums\ContractStatus;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\Signing\DocuSignSignatureProvider;
use App\Services\Signing\FakeSignatureProvider;
use App\Services\Signing\SignatureProvider;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

it('binds the FakeSignatureProvider when DocuSign is disabled', function () {
    app(SystemSettings::class)->set('integrations.docusign.enabled', false);
    app()->forgetInstance(SignatureProvider::class);

    expect(app(SignatureProvider::class))->toBeInstanceOf(FakeSignatureProvider::class);
});

it('binds the DocuSignSignatureProvider when DocuSign is enabled', function () {
    app(SystemSettings::class)->set('integrations.docusign.enabled', true);
    app()->forgetInstance(SignatureProvider::class);

    expect(app(SignatureProvider::class))->toBeInstanceOf(DocuSignSignatureProvider::class);
});

// connect webhook routing: each event walks the contract through its state

function postWebhook(array $payload): TestResponse
{
    return test()->postJson('/webhooks/docusign', $payload);
}

function preparedContract(): Contract
{
    $booking = Booking::factory()->create();
    $contract = Contract::query()->create([
        'booking_id' => $booking->id,
        'kind' => 'contract',
        'status' => ContractStatus::Sent->value,
        'total_cents' => 100_000,
        'rendered_html' => '<p>body</p>',
        'provider' => 'docusign',
        'provider_envelope_id' => 'env_'.bin2hex(random_bytes(8)),
        'sent_at' => now()->subHour(),
    ]);
    ContractSigner::query()->create([
        'contract_id' => $contract->id,
        'signing_order' => 1,
        'role' => 'client',
        'name' => 'Recipient One',
        'email' => 'r1@example.test',
    ]);

    return $contract;
}

it('marks a contract as viewed on recipient-delivered webhook', function () {
    $contract = preparedContract();
    $signer = $contract->signers->first();

    postWebhook([
        'event' => 'recipient-delivered',
        'data' => [
            'envelopeId' => $contract->provider_envelope_id,
            'recipientEmail' => $signer->email,
        ],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Viewed)
        ->and($signer->fresh()->viewed_at)->not->toBeNull();
});

it('marks a contract as signed when the last signer completes', function () {
    $contract = preparedContract();
    $signer = $contract->signers->first();

    postWebhook([
        'event' => 'recipient-completed',
        'data' => [
            'envelopeId' => $contract->provider_envelope_id,
            'recipientEmail' => $signer->email,
        ],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Signed)
        ->and($signer->fresh()->signed_at)->not->toBeNull();
});

it('marks a contract declined on recipient-declined webhook', function () {
    $contract = preparedContract();
    $signer = $contract->signers->first();

    postWebhook([
        'event' => 'recipient-declined',
        'data' => [
            'envelopeId' => $contract->provider_envelope_id,
            'recipientEmail' => $signer->email,
            'declinedReason' => 'Wrong date',
        ],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Declined)
        ->and($contract->fresh()->decline_reason)->toBe('Wrong date');
});

it('accepts envelope-completed without per-recipient context', function () {
    $contract = preparedContract();

    postWebhook([
        'event' => 'envelope-completed',
        'data' => ['envelopeId' => $contract->provider_envelope_id],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Signed);
});

it('does not resurrect a terminal contract on a late envelope-completed', function () {
    $contract = preparedContract();
    $contract->update(['status' => ContractStatus::Declined->value, 'declined_at' => now()]);

    postWebhook([
        'event' => 'envelope-completed',
        'data' => ['envelopeId' => $contract->provider_envelope_id],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Declined);
});

it('marks a contract voided on envelope-voided webhook', function () {
    $contract = preparedContract();

    postWebhook([
        'event' => 'envelope-voided',
        'data' => ['envelopeId' => $contract->provider_envelope_id],
    ])->assertOk();

    expect($contract->fresh()->status)->toBe(ContractStatus::Voided)
        ->and($contract->fresh()->voided_at)->not->toBeNull();
});

it('routes an addendum envelope-completed to the addendum, not the parent', function () {
    $parent = preparedContract();
    $parent->update([
        'status' => ContractStatus::Signed->value,
        'signed_at' => now()->subDay(),
    ]);

    // binding is keyed on provider_envelope_id, so the child's events must only touch the child
    $addendum = Contract::query()->create([
        'booking_id' => $parent->booking_id,
        'kind' => 'addendum',
        'parent_contract_id' => $parent->id,
        'status' => ContractStatus::Sent->value,
        'total_cents' => 0,
        'rendered_html' => '<p>addendum body</p>',
        'provider' => 'docusign',
        'provider_envelope_id' => 'env_'.bin2hex(random_bytes(8)),
        'sent_at' => now()->subHour(),
    ]);

    postWebhook([
        'event' => 'envelope-completed',
        'data' => ['envelopeId' => $addendum->provider_envelope_id],
    ])->assertOk();

    expect($addendum->fresh()->status)->toBe(ContractStatus::Signed);
    expect($parent->fresh()->status)->toBe(ContractStatus::Signed)
        ->and($parent->fresh()->signed_at?->toIso8601String())
        ->toBe($parent->signed_at?->toIso8601String());
});

it('voiding an addendum does not collaterally affect the parent', function () {
    $parent = preparedContract();
    $parent->update([
        'status' => ContractStatus::Signed->value,
        'signed_at' => now()->subDay(),
    ]);

    $addendum = Contract::query()->create([
        'booking_id' => $parent->booking_id,
        'kind' => 'addendum',
        'parent_contract_id' => $parent->id,
        'status' => ContractStatus::Sent->value,
        'total_cents' => 0,
        'rendered_html' => '<p>addendum body</p>',
        'provider' => 'docusign',
        'provider_envelope_id' => 'env_'.bin2hex(random_bytes(8)),
        'sent_at' => now()->subHour(),
    ]);

    postWebhook([
        'event' => 'envelope-voided',
        'data' => ['envelopeId' => $addendum->provider_envelope_id],
    ])->assertOk();

    expect($addendum->fresh()->status)->toBe(ContractStatus::Voided);
    expect($parent->fresh()->status)->toBe(ContractStatus::Signed);
});

it('returns 200 for unknown envelopes', function () {
    postWebhook([
        'event' => 'envelope-completed',
        'data' => ['envelopeId' => 'never-existed'],
    ])->assertOk()
        ->assertJson(['status' => 'ok', 'note' => 'unknown envelope']);
});

it('returns 422 when payload shape is invalid', function () {
    postWebhook(['nope' => true])->assertStatus(422);
});
