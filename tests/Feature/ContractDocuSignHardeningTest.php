<?php

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\Signing\SignatureProvider;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function postConnectWebhook(array $payload, ?string $hmacKey = null): TestResponse
{
    $raw = json_encode($payload);
    $server = ['CONTENT_TYPE' => 'application/json'];
    if ($hmacKey !== null) {
        $server['HTTP_X_DOCUSIGN_SIGNATURE_1'] = base64_encode(hash_hmac('sha256', $raw, $hmacKey, true));
    }

    return test()->call('POST', '/webhooks/docusign', [], [], [], $server, $raw);
}

// webhook HMAC authentication

it('rejects a webhook with a bad or missing signature when a key is configured', function () {
    app(SystemSettings::class)->set('integrations.docusign.connect_hmac_key', 'top-secret');
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create(['provider_envelope_id' => 'env_a']);
    $signer = ContractSigner::factory()->create(['contract_id' => $contract->id, 'email' => 'sign@x.test']);
    $payload = ['event' => 'recipient-completed', 'data' => ['envelopeId' => 'env_a', 'recipientEmail' => 'sign@x.test']];

    // missing signature
    postConnectWebhook($payload)->assertStatus(401);
    // wrong signature
    test()->call('POST', '/webhooks/docusign', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_DOCUSIGN_SIGNATURE_1' => 'not-it',
    ], json_encode($payload))->assertStatus(401);

    expect($contract->fresh()->status)->toBe(ContractStatus::Sent);
});

it('accepts a webhook with a valid signature', function () {
    app(SystemSettings::class)->set('integrations.docusign.connect_hmac_key', 'top-secret');
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create(['provider_envelope_id' => 'env_b']);

    postConnectWebhook(['event' => 'recipient-sent', 'data' => ['envelopeId' => 'env_b']], 'top-secret')->assertOk();
});

it('allows an unsigned webhook when no key is configured', function () {
    // no key + DocuSign disabled: the fake-driver demo posts unsigned
    postConnectWebhook(['event' => 'recipient-sent', 'data' => ['envelopeId' => 'env_unknown']])->assertOk();
});

it('rejects an unsigned webhook when DocuSign is live without an HMAC key', function () {
    // enabled + no key must fail closed; an unsigned webhook could forge a status change
    app(SystemSettings::class)->set('integrations.docusign.enabled', true);
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create(['provider_envelope_id' => 'env_fc']);

    postConnectWebhook(['event' => 'envelope-completed', 'data' => ['envelopeId' => 'env_fc']])
        ->assertStatus(401);

    expect($contract->fresh()->status)->toBe(ContractStatus::Sent);
})->group('stig', 'nist-SI-10');

// executed PDF retrieval + storage

it('captures and stores the executed PDF when a contract is fully signed', function () {
    Storage::fake();
    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create(['provider_envelope_id' => 'env_c']);
    $signer = ContractSigner::factory()->create([
        'contract_id' => $contract->id,
        'email' => 'last@x.test',
        'signed_at' => null,
    ]);

    postConnectWebhook(['event' => 'recipient-completed', 'data' => ['envelopeId' => 'env_c', 'recipientEmail' => 'last@x.test']])
        ->assertOk();

    $fresh = $contract->fresh();
    expect($fresh->status)->toBe(ContractStatus::Signed)
        ->and($fresh->pdf_s3_key)->not->toBeNull();
    Storage::assertExists($fresh->pdf_s3_key);
});

it('streams the stored signed PDF and 404s when none exists', function () {
    Storage::fake();
    $user = grantSuperAdmin();
    $withPdf = Contract::factory()->inStatus(ContractStatus::Signed)->create(['pdf_s3_key' => 'contracts/9/x-signed.pdf']);
    Storage::put('contracts/9/x-signed.pdf', '%PDF fake');
    $withoutPdf = Contract::factory()->inStatus(ContractStatus::Signed)->create(['pdf_s3_key' => null]);

    $this->actingAs($user)->get("/contracts/{$withPdf->id}/signed-pdf")->assertOk();
    $this->actingAs($user)->get("/contracts/{$withoutPdf->id}/signed-pdf")->assertNotFound();
});

// reconciliation backstop

it('finalizes a completed envelope via the reconciliation job and stores the PDF', function () {
    Storage::fake();
    $this->mock(SignatureProvider::class, function ($mock) {
        $mock->shouldReceive('getEnvelopeStatus')->andReturn('completed');
        $mock->shouldReceive('downloadSignedDocument')->andReturn('%PDF reconciled');
    });

    $contract = Contract::factory()->inStatus(ContractStatus::Sent)->create(['provider_envelope_id' => 'env_d']);

    $this->artisan('contracts:reconcile-signatures')->assertSuccessful();

    $fresh = $contract->fresh();
    expect($fresh->status)->toBe(ContractStatus::Signed)
        ->and($fresh->pdf_s3_key)->not->toBeNull();
    Storage::assertExists($fresh->pdf_s3_key);
});

// graceful send errors

it('surfaces a provider failure on send as a form error', function () {
    $this->mock(SignatureProvider::class, function ($mock) {
        $mock->shouldReceive('createEnvelope')->andThrow(new RuntimeException('DocuSign down'));
    });

    $contract = Contract::factory()->inStatus(ContractStatus::Draft)->create();

    $this->actingAs(grantSuperAdmin())
        ->post("/contracts/{$contract->id}/send", [
            'signers' => [['name' => 'Pat', 'email' => 'pat@x.test']],
        ])
        ->assertSessionHasErrors('send');

    expect($contract->fresh()->status)->toBe(ContractStatus::Draft);
});
