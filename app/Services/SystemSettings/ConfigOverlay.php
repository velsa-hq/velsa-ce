<?php

namespace App\Services\SystemSettings;

use Illuminate\Contracts\Config\Repository;

/**
 * Overlays DB-stored system settings onto the runtime config repository, so
 * config() reads return the admin-UI value or fall back to the env default.
 * MAP is the source of truth for setting -> config key; keep it in sync.
 */
class ConfigOverlay
{
    /** @var array<string, string> system-setting key => config key */
    protected const MAP = [
        // SSO meta
        'integrations.sso.enabled' => 'sso.enabled',
        'integrations.sso.provisioning' => 'sso.provisioning',
        'integrations.sso.default_role' => 'sso.default_role',
        'integrations.sso.bootstrap_admin_role' => 'sso.bootstrap_admin_role',

        // Microsoft Entra
        'integrations.microsoft.enabled' => 'sso.providers.microsoft.enabled',
        'integrations.microsoft.tenant' => 'sso.providers.microsoft.tenant',
        'integrations.microsoft.client_id' => 'sso.providers.microsoft.client_id',
        'integrations.microsoft.client_secret' => 'sso.providers.microsoft.client_secret',
        'integrations.microsoft.redirect' => 'sso.providers.microsoft.redirect',

        // DocuSign
        'integrations.docusign.enabled' => 'services.docusign.enabled',
        'integrations.docusign.base_uri' => 'services.docusign.base_uri',
        'integrations.docusign.oauth_base' => 'services.docusign.oauth_base',
        'integrations.docusign.integration_key' => 'services.docusign.integration_key',
        'integrations.docusign.secret_key' => 'services.docusign.secret_key',
        'integrations.docusign.keypair_id' => 'services.docusign.keypair_id',
        'integrations.docusign.user_id' => 'services.docusign.user_id',
        'integrations.docusign.account_id' => 'services.docusign.account_id',

        // Meilisearch (global search via Laravel Scout)
        'integrations.meilisearch.host' => 'scout.meilisearch.host',
        'integrations.meilisearch.key' => 'scout.meilisearch.key',
        'integrations.meilisearch.prefix' => 'scout.prefix',

        // Postmark (transactional email)
        'integrations.postmark.token' => 'services.postmark.key',
        'integrations.postmark.from_email' => 'mail.from.address',

        // Payment gateway credentials (the active gateway is selected below)
        'integrations.bluepay.merchant_id' => 'payments.bluepay.merchant_id',
        'integrations.bluepay.secret_key' => 'payments.bluepay.secret_key',
        'integrations.bluepay.mode' => 'payments.bluepay.mode',
        'integrations.bluepay.hash_type' => 'payments.bluepay.hash_type',
        'integrations.stripe.secret' => 'payments.stripe.secret',
        'integrations.stripe.currency' => 'payments.stripe.currency',
        'integrations.cardpointe.site' => 'payments.cardpointe.site',
        'integrations.cardpointe.merchant_id' => 'payments.cardpointe.merchant_id',
        'integrations.cardpointe.username' => 'payments.cardpointe.username',
        'integrations.cardpointe.password' => 'payments.cardpointe.password',
        'integrations.cardpointe.environment' => 'payments.cardpointe.environment',
    ];

    public function __construct(
        protected SystemSettings $settings,
        protected SystemSettingsRegistry $registry,
    ) {}

    public function apply(Repository $config): void
    {
        foreach (self::MAP as $settingKey => $configKey) {
            if (! $this->registry->has($settingKey)) {
                continue;
            }
            $raw = $this->settings->getStored($settingKey);
            if ($raw === null) {
                continue;
            }

            // has() checked above, so non-null
            $def = $this->registry->get($settingKey);
            $config->set($configKey, $this->coerce($raw, $def->type));
        }

        // bootstrap_admin_emails is stored as CSV; explode to the array shape
        // consumers expect, only when a DB row exists so the env array isn't clobbered
        $csv = $this->settings->getStored('integrations.sso.bootstrap_admin_emails');
        if (is_string($csv) && $csv !== '') {
            $config->set('sso.bootstrap_admin_emails', array_values(array_filter(array_map(
                'trim',
                explode(',', $csv),
            ))));
        }

        // email transport selector; "default"/unset leaves the env mailer in place.
        // safe mode overrides mail.default afterwards, so Demo/Training never sends
        switch ($this->settings->get('integrations.mail.transport')) {
            case 'postmark':
                // token + from address already overlaid via MAP
                $config->set('mail.default', 'postmark');
                $this->applyFromName($config);
                break;

            case 'microsoft365':
                $config->set('mail.default', 'smtp');
                $config->set('mail.mailers.smtp.host', 'smtp.office365.com');
                $config->set('mail.mailers.smtp.port', 587);
                $config->set('mail.mailers.smtp.username', $this->settings->getStored('integrations.microsoft365.username'));
                $config->set('mail.mailers.smtp.password', $this->settings->getStored('integrations.microsoft365.password'));
                $from = $this->settings->getStored('integrations.microsoft365.from_email');
                if (is_string($from) && $from !== '') {
                    $config->set('mail.from.address', $from);
                }
                $this->applyFromName($config);
                break;
        }

        // payment gateway selector; "none"/unset leaves the env processor (fake stub) in place
        switch ($this->settings->get('integrations.payments.gateway')) {
            case 'cardpointe':
                $config->set('payments.processor', 'cardpointe');
                break;
            case 'bluepay':
                $config->set('payments.processor', 'bluepay');
                break;
            case 'stripe':
                $config->set('payments.processor', 'stripe');
                break;
        }
    }

    /**
     * Reuse the branding subtitle as the mail "from" name to avoid a duplicate
     * field in the settings UI.
     */
    protected function applyFromName(Repository $config): void
    {
        $fromName = $this->settings->get('branding.app_subtitle');
        if (is_string($fromName) && $fromName !== '') {
            $config->set('mail.from.name', $fromName);
        }
    }

    protected function coerce(string $raw, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $raw,
            'boolean' => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            default => $raw,
        };
    }
}
