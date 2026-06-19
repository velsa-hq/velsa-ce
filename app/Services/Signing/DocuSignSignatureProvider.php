<?php

namespace App\Services\Signing;

use App\Models\Contract;
use App\Models\ContractSigner;
use App\Services\SystemSettings\SystemSettings;
use DocuSign\eSign\Api\EnvelopesApi;
use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Configuration;
use DocuSign\eSign\Model\Checkbox;
use DocuSign\eSign\Model\DateSigned;
use DocuSign\eSign\Model\Document;
use DocuSign\eSign\Model\EnvelopeDefinition;
use DocuSign\eSign\Model\InitialHere;
use DocuSign\eSign\Model\Recipients;
use DocuSign\eSign\Model\Signer;
use DocuSign\eSign\Model\SignHere;
use DocuSign\eSign\Model\Tabs;
use RuntimeException;

/**
 * DocuSign-backed SignatureProvider.
 *
 * Auth: JWT grant via docusign/esign-client. Integration key, user id, account
 * id, base URIs live in SystemSettings; the RSA private key is on disk at
 * storage/app/keys/docusign-private.pem (never committed).
 *
 * Delivery: the contract's rendered_html is sent as an .html document and
 * DocuSign renders to PDF server-side.
 *
 * Bound conditionally in AppServiceProvider on integrations.docusign.enabled;
 * with the toggle off FakeSignatureProvider stays the default, so tests and
 * unconfigured environments never hit a real DocuSign API.
 */
class DocuSignSignatureProvider implements SignatureProvider
{
    /**
     * Anchor strings the primary signer's tabs attach to. DocuSign places each
     * field next to the matching literal in the rendered document, so templates
     * only print the label - no coordinate math. Missing anchors are tolerated
     * (no tab, no error), so older templates without a block still send fine.
     */
    public const ANCHOR_SIGNATURE = 'Signature:';

    public const ANCHOR_INITIALS = 'Initials:';

    public const ANCHOR_DATE = 'Date:';

    public const ANCHOR_AGREE = 'agree to the terms';

    /**
     * Lazy SDK client + token holder. Built once per process; the JWT token is
     * good for an hour so we don't re-authenticate per envelope.
     */
    protected ?ApiClient $apiClient = null;

    public function __construct(protected SystemSettings $settings) {}

    public function createEnvelope(Contract $contract): SignatureEnvelope
    {
        $envelopesApi = new EnvelopesApi($this->client());
        $accountId = $this->setting('integrations.docusign.account_id');

        // send body as HTML; DocuSign converts to PDF server-side
        $body = (string) ($contract->rendered_html ?? '');
        if ($body === '') {
            throw new RuntimeException(
                "Contract {$contract->reference} has no rendered body to send.",
            );
        }

        $document = new Document([
            'document_base64' => base64_encode($body),
            'name' => "Contract {$contract->reference}",
            'file_extension' => 'html',
            'document_id' => '1',
        ]);

        // one DocuSign signer per ContractSigner row; primary gets the full
        // field set, additional signers a per-recipient SignHere (see tabsForSigner)
        $ordered = $contract->signers->sortBy('signing_order')->values();

        $signers = [];
        $recipientId = 1;
        /** @var ContractSigner $row */
        foreach ($ordered as $row) {
            $signers[] = new Signer([
                'email' => $row->email,
                'name' => $row->name,
                'recipient_id' => (string) $recipientId,
                'routing_order' => (string) ($row->signing_order ?? $recipientId),
                'tabs' => $this->tabsForSigner($recipientId, isPrimary: $recipientId === 1),
            ]);
            $recipientId++;
        }

        $envelope = new EnvelopeDefinition([
            'email_subject' => "Contract {$contract->reference} - please review and sign",
            'documents' => [$document],
            'recipients' => new Recipients(['signers' => $signers]),
            'status' => 'sent',
        ]);

        try {
            $result = $envelopesApi->createEnvelope($accountId, $envelope);
        } catch (ApiException $e) {
            throw new RuntimeException(
                "DocuSign envelope creation failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $envelopeId = $result->getEnvelopeId();

        return new SignatureEnvelope(
            envelopeId: $envelopeId,
            status: $result->getStatus() ?: 'sent',
            signers: array_map(
                fn (Signer $s) => [
                    'recipient_id' => $s->getRecipientId(),
                    'name' => $s->getName(),
                    'email' => $s->getEmail(),
                    // envelope creation returns the envelope id only; recipients
                    // sign via the email DocuSign mails, not a URL we hand them
                    'signing_url' => '',
                ],
                $signers,
            ),
        );
    }

    /**
     * Build the field set for one signer.
     *
     * Primary signer gets signature, initials, auto-filled date, and a required
     * "I agree" checkbox anchored to the template's signature block. Additional
     * signers get a SignHere on a per-recipient token (\sN\), absent from the
     * default single-block template, so counter-signers don't overlap the
     * primary block or trip an anchor-not-found error.
     */
    protected function tabsForSigner(int $recipientId, bool $isPrimary): Tabs
    {
        if (! $isPrimary) {
            return new Tabs([
                'sign_here_tabs' => [new SignHere([
                    'anchor_string' => "\\s{$recipientId}\\",
                    'anchor_units' => 'pixels',
                    'anchor_x_offset' => '0',
                    'anchor_y_offset' => '0',
                ])],
            ]);
        }

        return new Tabs([
            'sign_here_tabs' => [new SignHere([
                'anchor_string' => self::ANCHOR_SIGNATURE,
                'anchor_units' => 'pixels',
                'anchor_x_offset' => '8',
                'anchor_y_offset' => '-6',
            ])],
            'initial_here_tabs' => [new InitialHere([
                'anchor_string' => self::ANCHOR_INITIALS,
                'anchor_units' => 'pixels',
                'anchor_x_offset' => '8',
                'anchor_y_offset' => '-6',
            ])],
            'date_signed_tabs' => [new DateSigned([
                'anchor_string' => self::ANCHOR_DATE,
                'anchor_units' => 'pixels',
                'anchor_x_offset' => '8',
                'anchor_y_offset' => '-6',
            ])],
            'checkbox_tabs' => [new Checkbox([
                'anchor_string' => self::ANCHOR_AGREE,
                'anchor_units' => 'pixels',
                'anchor_x_offset' => '-18',
                'anchor_y_offset' => '0',
                'name' => 'agree_terms',
                'tab_label' => 'agree_terms',
                'required' => 'true',
            ])],
        ]);
    }

    public function getEnvelopeStatus(string $envelopeId): string
    {
        $envelopesApi = new EnvelopesApi($this->client());
        $accountId = $this->setting('integrations.docusign.account_id');

        try {
            $envelope = $envelopesApi->getEnvelope($accountId, $envelopeId);
        } catch (ApiException $e) {
            throw new RuntimeException(
                "DocuSign envelope status fetch failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        return $envelope->getStatus() ?: 'unknown';
    }

    public function downloadSignedDocument(string $envelopeId): string
    {
        $envelopesApi = new EnvelopesApi($this->client());
        $accountId = $this->setting('integrations.docusign.account_id');

        try {
            // 'combined' yields the full executed PDF (all documents + cert of completion)
            $file = $envelopesApi->getDocument($accountId, 'combined', $envelopeId);
        } catch (ApiException $e) {
            throw new RuntimeException(
                "DocuSign document download failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $path = $file->getRealPath();
        $contents = $path !== false ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("DocuSign returned no document content for envelope {$envelopeId}.");
        }

        return $contents;
    }

    /**
     * Build (or reuse) the authenticated SDK client. JWT auth swaps an
     * integration key + signed JWT for a short-lived access token.
     */
    protected function client(): ApiClient
    {
        if ($this->apiClient !== null) {
            return $this->apiClient;
        }

        $integrationKey = $this->setting('integrations.docusign.integration_key');
        $userId = $this->setting('integrations.docusign.user_id');
        $privateKeyPath = storage_path('app/keys/docusign-private.pem');

        if (! is_readable($privateKeyPath)) {
            throw new RuntimeException(
                "DocuSign private key not found at {$privateKeyPath}.",
            );
        }
        $privateKey = (string) file_get_contents($privateKeyPath);

        $apiClient = $this->buildApiClient();

        try {
            $response = $apiClient->requestJWTUserToken(
                $integrationKey,
                $userId,
                $privateKey,
                ['signature', 'impersonation'],
                3600,
            );
        } catch (ApiException $e) {
            throw new RuntimeException(
                "DocuSign JWT grant failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        // SDK returns the access token at response[0]; seed it as a bearer header
        $token = (string) $response[0]->getAccessToken();
        $apiClient->getConfig()->addDefaultHeader('Authorization', "Bearer {$token}");

        return $this->apiClient = $apiClient;
    }

    /**
     * Build the unauthenticated SDK client with REST and OAuth hosts pinned from
     * settings. Split from client() so host wiring is assertable without a
     * network round-trip.
     *
     * The OAuth helper does NOT inherit the Configuration REST host, so without
     * an explicit base path the JWT grant's aud claim derives to the production
     * host (account.docusign.com) and a demo/sandbox key fails with issuer_not_found.
     */
    protected function buildApiClient(): ApiClient
    {
        $baseUri = $this->setting('integrations.docusign.base_uri');
        $oauthBase = $this->setting('integrations.docusign.oauth_base');

        $config = new Configuration;
        $config->setHost($baseUri);
        $apiClient = new ApiClient($config);
        $apiClient->getOAuth()->setOAuthBasePath((string) preg_replace('#^https?://#', '', $oauthBase));

        return $apiClient;
    }

    protected function setting(string $key): string
    {
        $value = $this->settings->get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException(
                "DocuSign setting `{$key}` is not configured.",
            );
        }

        return (string) $value;
    }
}
