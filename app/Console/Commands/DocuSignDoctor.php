<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\Signing\ContractDispatcher;
use App\Services\Signing\SignatureProvider;
use App\Services\SystemSettings\SystemSettings;
use DocuSign\eSign\Client\ApiClient;
use DocuSign\eSign\Client\ApiException;
use DocuSign\eSign\Configuration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Live-connectivity diagnostic for the DocuSign integration (the test suite runs
 * against FakeSignatureProvider, so this exercises the real provider). Three
 * independently gated modes:
 *
 *   docusign:doctor                       config audit + JWT auth probe; first
 *                                         run returns consent_required and prints
 *                                         the one-time admin consent URL
 *   docusign:doctor --send=a@b.com        sends a real envelope (latest draft or
 *                                         --contract=ID) for a human to sign
 *   docusign:doctor --status=<envelopeId> fetches live envelope status
 *
 * After signing, contracts:reconcile-signatures flips the contract to Signed and
 * stores the executed PDF, no public Connect webhook needed.
 */
#[Signature('docusign:doctor {--send= : Email address to send a real test envelope to} {--name=DocuSign Test Signer : Signer name for the test envelope} {--contract= : Contract id to use for the test envelope (default: latest draft)} {--status= : Fetch the live status of an existing envelope id} {--redirect= : Consent redirect URI (must be registered on the integration key; default app.url)}')]
#[Description('Diagnose the live DocuSign integration: config audit, JWT auth probe, and optional real-envelope round-trip')]
class DocuSignDoctor extends Command
{
    public function handle(SystemSettings $settings, SignatureProvider $provider, ContractDispatcher $dispatcher): int
    {
        $this->components->info('DocuSign integration doctor');

        if (! $this->auditConfig($settings)) {
            return self::FAILURE;
        }

        if ($status = $this->option('status')) {
            return $this->checkStatus($provider, (string) $status);
        }

        if ($email = $this->option('send')) {
            return $this->sendTestEnvelope($dispatcher, (string) $email);
        }

        return $this->probeAuth($settings);
    }

    /**
     * Report which settings resolve (masked) and that the RSA key is on disk.
     * Returns false if a hard prerequisite is missing.
     */
    private function auditConfig(SystemSettings $settings): bool
    {
        $enabled = (bool) $settings->get('integrations.docusign.enabled', false);
        $rows = [];
        $required = [
            'integrations.docusign.integration_key' => true,
            'integrations.docusign.user_id' => true,
            'integrations.docusign.account_id' => true,
            'integrations.docusign.base_uri' => true,
            'integrations.docusign.oauth_base' => true,
            'integrations.docusign.keypair_id' => false,
            'integrations.docusign.connect_hmac_key' => false,
        ];

        $missing = false;
        foreach ($required as $key => $isRequired) {
            $value = (string) ($settings->get($key) ?? '');
            $present = $value !== '';
            if ($isRequired && ! $present) {
                $missing = true;
            }
            $rows[] = [
                str_replace('integrations.docusign.', '', $key).($isRequired ? ' *' : ''),
                $present ? $this->mask($value) : '<fg=red>(missing)</>',
            ];
        }

        $keyPath = storage_path('app/keys/docusign-private.pem');
        $keyReadable = is_readable($keyPath);
        $rows[] = ['private key (disk)', $keyReadable ? '<fg=green>readable</>' : '<fg=red>'.$keyPath.' not readable</>'];
        $rows[] = ['enabled', $enabled ? '<fg=green>true</>' : '<fg=yellow>false</>'];

        $this->table(['setting (* required)', 'value'], $rows);

        if (! $enabled) {
            $this->components->warn('integrations.docusign.enabled is false - the app uses the Fake provider. Set DOCUSIGN_ENABLED=true to exercise real DocuSign.');
        }
        if (! $keyReadable) {
            $this->components->error("RSA private key not readable at {$keyPath}.");

            return false;
        }
        if ($missing) {
            $this->components->error('One or more required settings are missing (marked *). Fill them in .env or System Settings, then re-run.');

            return false;
        }

        return true;
    }

    /**
     * Attempt a JWT user token grant. consent_required is expected on first run;
     * surface the consent URL rather than treat it as fatal.
     */
    private function probeAuth(SystemSettings $settings): int
    {
        $integrationKey = (string) $settings->get('integrations.docusign.integration_key');
        $userId = (string) $settings->get('integrations.docusign.user_id');
        $oauthBase = (string) $settings->get('integrations.docusign.oauth_base');
        $baseUri = (string) $settings->get('integrations.docusign.base_uri');
        $privateKey = (string) file_get_contents(storage_path('app/keys/docusign-private.pem'));

        $config = new Configuration;
        $config->setHost($baseUri);
        $apiClient = new ApiClient($config);
        // JWT aud claim is the OAuth host (no scheme); set explicitly so configured oauth_base wins over the SDK default
        $apiClient->getOAuth()->setOAuthBasePath((string) preg_replace('#^https?://#', '', $oauthBase));

        $this->line('Requesting JWT user token...');

        try {
            $response = $apiClient->requestJWTUserToken(
                $integrationKey,
                $userId,
                $privateKey,
                ['signature', 'impersonation'],
                3600,
            );
        } catch (ApiException $e) {
            $raw = $e->getResponseBody();
            $body = is_string($raw) ? $raw : (string) json_encode($raw);
            if (str_contains($body, 'consent_required')) {
                $this->components->warn('Consent required - this is expected on first setup.');
                $this->printConsentUrl($integrationKey, $oauthBase);

                return self::FAILURE;
            }
            $this->components->error("JWT grant failed: {$e->getMessage()}");
            if ($body !== '') {
                $this->line("  response: {$body}");
            }

            return self::FAILURE;
        }

        $token = (string) $response[0]->getAccessToken();
        $this->components->info('JWT auth OK - access token acquired ('.$this->mask($token).').');
        $this->line('Auth is proven. Next: php artisan docusign:doctor --send=you@example.com');

        return self::SUCCESS;
    }

    private function sendTestEnvelope(ContractDispatcher $dispatcher, string $email): int
    {
        $contractId = $this->option('contract');
        $contract = $contractId !== null
            ? Contract::query()->find($contractId)
            : Contract::query()->where('status', ContractStatus::Draft->value)->latest('id')->first();

        if ($contract === null) {
            $this->components->error('No draft contract found. Pass --contract=ID, or create a draft first.');

            return self::FAILURE;
        }

        if ((string) ($contract->rendered_html ?? '') === '' && $contract->template === null) {
            $this->components->error("Contract {$contract->reference} has no rendered body and no template to render from.");

            return self::FAILURE;
        }

        $name = (string) $this->option('name');
        $this->line("Sending contract {$contract->reference} to {$email} via the live provider...");

        try {
            $envelope = $dispatcher->send($contract, [
                ['name' => $name, 'email' => $email, 'role' => 'client', 'signing_order' => 1],
            ]);
        } catch (\Throwable $e) {
            $this->components->error("Envelope send failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Envelope sent. envelope_id={$envelope->envelopeId} status={$envelope->status}");
        $this->line("Check {$email} for the DocuSign email, sign it, then run:");
        $this->line('  php artisan contracts:reconcile-signatures');
        $this->line("Or poll status with: php artisan docusign:doctor --status={$envelope->envelopeId}");

        return self::SUCCESS;
    }

    private function checkStatus(SignatureProvider $provider, string $envelopeId): int
    {
        try {
            $status = $provider->getEnvelopeStatus($envelopeId);
        } catch (\Throwable $e) {
            $this->components->error("Status fetch failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Envelope {$envelopeId} status: {$status}");

        return self::SUCCESS;
    }

    private function printConsentUrl(string $integrationKey, string $oauthBase): void
    {
        $redirect = (string) ($this->option('redirect') ?: config('app.url'));
        $url = rtrim($oauthBase, '/').'/oauth/auth?'.http_build_query([
            'response_type' => 'code',
            'scope' => 'signature impersonation',
            'client_id' => $integrationKey,
            'redirect_uri' => $redirect,
        ]);

        $this->newLine();
        $this->line('Open this URL once in a browser, sign in as the API user, and click <options=bold>Allow</>:');
        $this->line("  <fg=cyan>{$url}</>");
        $this->newLine();
        $this->line("The redirect URI (<fg=yellow>{$redirect}</>) must be registered on the integration key");
        $this->line('(DocuSign Admin -> Apps and Keys -> your app -> Additional settings -> Redirect URIs).');
        $this->line('Override it with --redirect=... if needed. Then re-run: php artisan docusign:doctor');
    }

    private function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($value, 0, 4).'...'.substr($value, -4)." (len={$len})";
    }
}
