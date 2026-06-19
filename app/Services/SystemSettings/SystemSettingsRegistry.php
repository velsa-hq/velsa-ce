<?php

namespace App\Services\SystemSettings;

use App\Dashboard\DashboardTileRegistry;

/**
 * Canonical catalog of every tunable system setting. Anything not declared
 * here can't be set via the admin UI or read through SystemSettings - the
 * registry is the trust boundary. Integration secrets are encrypted at rest
 * and write-only (rendered masked, round-tripped via a KEEP_CURRENT sentinel).
 */
class SystemSettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    protected array $definitions = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /** @return array<string, SettingDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function get(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    /**
     * Definitions grouped by category, ordered by registration.
     *
     * @return array<string, list<SettingDefinition>>
     */
    public function byCategory(): array
    {
        $grouped = [];
        foreach ($this->definitions as $def) {
            $grouped[$def->category][] = $def;
        }

        return $grouped;
    }

    protected function register(SettingDefinition $def): void
    {
        $this->definitions[$def->key] = $def;
    }

    protected function registerDefaults(): void
    {
        // -- Branding -----------------------------------------------
        $this->register(new SettingDefinition(
            key: 'branding.app_name',
            category: 'branding',
            label: 'Application name',
            description: 'Shown in the browser tab title, the navigation bar, and outbound emails.',
            default: 'Velsa',
            envKey: 'APP_NAME',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.app_title',
            category: 'branding',
            label: 'Login-page title',
            description: 'Large title rendered on the branded auth layout. E.g. the organization name.',
            default: 'Your organization',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.app_subtitle',
            category: 'branding',
            label: 'Login-page subtitle',
            description: 'Second line under the title. E.g. department + system name.',
            default: 'Velsa',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.app_tagline',
            category: 'branding',
            label: 'Login-page tagline',
            description: 'Longer descriptive line at the bottom of the branded hero.',
            default: 'One system for venues, bookings, contracts, and operations.',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.logo_path',
            category: 'branding',
            label: 'Primary logo path',
            description: 'Path under /branding/ for the primary mark shown on the auth + welcome pages. E.g. /branding/your-org/seal.svg.',
            default: '/favicon.svg',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.logo_alt',
            category: 'branding',
            label: 'Primary logo alt text',
            description: 'Accessible text describing the logo for screen readers.',
            default: 'Organization seal',
        ));
        $this->register(new SettingDefinition(
            key: 'branding.stock_background_folder',
            category: 'branding',
            label: 'Login background image folder',
            description: 'Public folder path containing the stock images randomly chosen for the auth-page background. Looks for *.webp files.',
            default: 'branding/stock',
        ));

        // -- Defaults ----------------------------------------------
        $this->register(new SettingDefinition(
            key: 'defaults.fiscal_year_start_month',
            category: 'defaults',
            label: 'Fiscal year start month',
            description: 'Month that the fiscal year begins. 10 = October (US federal default).',
            type: 'integer',
            default: 10,
            options: ['min' => 1, 'max' => 12],
        ));
        $this->register(new SettingDefinition(
            key: 'operations.read_only',
            category: 'operations',
            label: 'Read-only mode',
            description: 'Temporarily block all interactive changes (creating, editing, deleting) across the app - used during data imports, backups, or maintenance. Sign-in, data import, and external webhooks still work; turn this off here to resume normal operation.',
            type: 'boolean',
            default: false,
        ));
        $this->register(new SettingDefinition(
            key: 'operations.venue_isolation',
            category: 'operations',
            label: 'Venue data isolation',
            description: 'Restrict each user to the data of the venues they have a role at - bookings, spaces, work orders, leads, inventory, rate cards, and templates from other venues are hidden. Org-wide roles (those with the "view all venues" permission, e.g. county/super admins) still see everything. Off by default: leave it off to let all staff see every venue under one operation.',
            type: 'boolean',
            default: false,
        ));
        // -- Security ------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'security.login_banner_enabled',
            category: 'security',
            label: 'Login consent banner',
            description: 'Show a system-use / consent notice on the login page that the user must acknowledge before signing in (STIG AC-8). Off by default; enable for government deployments.',
            type: 'boolean',
            default: false,
        ));
        $this->register(new SettingDefinition(
            key: 'security.login_banner_text',
            category: 'security',
            label: 'Login consent banner text',
            description: 'The notice shown when the login consent banner is enabled. Set your organization\'s authorized-use / monitoring-consent language (or the Standard Mandatory DoD Notice for DoD deployments).',
            default: 'This is an authorized system. By signing in you consent to monitoring of your activity and acknowledge that unauthorized use is prohibited and may be subject to criminal and civil penalties.',
        ));
        $this->register(new SettingDefinition(
            key: 'security.account_notifications_enabled',
            category: 'security',
            label: 'Account-change notifications',
            description: 'Email designated security staff when a user account is created, modified, disabled, or enabled (STIG AC-2(1)/(4)). Off by default; enable and set recipients for government deployments.',
            type: 'boolean',
            default: false,
        ));
        $this->register(new SettingDefinition(
            key: 'security.account_notification_recipients',
            category: 'security',
            label: 'Account-change notification recipients',
            description: 'Email addresses (comma-separated) of the SAs/ISSOs who receive account-change notifications. No notifications are sent if this is empty.',
            default: '',
        ));
        $this->register(new SettingDefinition(
            key: 'security.portal_magic_link_ttl_days',
            category: 'security',
            label: 'Exhibitor portal link lifetime (days)',
            description: 'How many days an exhibitor portal sign-in link stays valid before it expires. Shorter is more secure; default 7.',
            type: 'integer',
            default: 7,
            options: ['min' => 1, 'max' => 90],
        ));
        // -- Support -------------------------------------------------
        $this->register(new SettingDefinition(
            key: 'support.notifications_enabled',
            category: 'support',
            label: 'Support request notifications',
            description: 'Email the support contacts when a user submits an in-app support request. Requests are always recorded for review under Admin -> Support requests; this only controls email delivery.',
            type: 'boolean',
            default: false,
            group: 'support_notifications',
            groupLabel: 'Support request notifications',
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'support.recipients',
            category: 'support',
            label: 'Support request recipients',
            description: 'Email addresses (comma-separated) that receive new support requests. If empty, no email is sent - requests are still recorded for review.',
            default: '',
            group: 'support_notifications',
            groupLabel: 'Support request notifications',
        ));
        // -- Compliance ----------------------------------------------
        $this->register(new SettingDefinition(
            key: 'compliance.expiry_reminders_enabled',
            category: 'compliance',
            label: 'Certificate expiry reminders',
            description: 'Email the compliance contacts a digest of insurance certificates expiring soon. Certificates are still tracked and auto-expired regardless; this only controls the reminder email.',
            type: 'boolean',
            default: false,
            group: 'compliance_reminders',
            groupLabel: 'Insurance certificate reminders',
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'compliance.expiry_reminder_days',
            category: 'compliance',
            label: 'Expiry reminder window (days)',
            description: 'How many days before expiry a certificate counts as "expiring soon" - both for the reminder digest and the admin list filter.',
            type: 'integer',
            default: 30,
            options: ['min' => 1, 'max' => 365],
            group: 'compliance_reminders',
            groupLabel: 'Insurance certificate reminders',
        ));
        $this->register(new SettingDefinition(
            key: 'compliance.notification_recipients',
            category: 'compliance',
            label: 'Compliance contacts',
            description: 'Email addresses (comma-separated) that receive the certificate-expiry digest. If empty, no email is sent - certificates are still tracked.',
            default: '',
            group: 'compliance_reminders',
            groupLabel: 'Insurance certificate reminders',
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.use_metric_units',
            category: 'defaults',
            label: 'Use metric units (m²)',
            description: 'Show and accept space areas in square metres instead of square feet. Stored values are unaffected - only how areas are displayed and entered changes.',
            type: 'boolean',
            default: false,
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.idle_timeout_minutes',
            category: 'defaults',
            label: 'Idle-timeout (minutes)',
            description: 'Automatically sign a user out after this many minutes of inactivity. Set to 0 to disable.',
            type: 'integer',
            default: 15,
            options: ['min' => 0, 'max' => 240],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.contract_expiry_after_days',
            category: 'defaults',
            label: 'Contract expiry window (days)',
            description: 'A contract sent for signature is automatically marked Expired if it has not been completed within this many days.',
            type: 'integer',
            default: 30,
            options: ['min' => 1, 'max' => 365],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.pipeline_archive_after_days',
            category: 'defaults',
            label: 'Pipeline archive window (days)',
            description: 'Closed (Won/Lost) opportunities age off the pipeline board into the archive once they have been closed this many days. Opportunities whose event date is still in the future are kept on the board regardless.',
            type: 'integer',
            default: 60,
            options: ['min' => 1, 'max' => 3650],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.pipeline_overdue_grace_days',
            category: 'defaults',
            label: 'Pipeline overdue grace (days)',
            description: 'An open opportunity is flagged overdue on the board once its expected close date is this many days in the past. 0 marks it overdue the day after its close date.',
            type: 'integer',
            default: 0,
            options: ['min' => 0, 'max' => 365],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.needs_attention_booking_stale_days',
            category: 'defaults',
            label: 'Stale tentative-booking window (days)',
            description: 'The dashboard "Needs attention" tile flags a tentative booking as going cold when it has had no narrative activity for this many days.',
            type: 'integer',
            default: 14,
            options: ['min' => 1, 'max' => 365],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.needs_attention_contract_unviewed_days',
            category: 'defaults',
            label: 'Unopened-contract window (days)',
            description: 'The dashboard "Needs attention" tile flags a sent contract as unopened when it has been sent but not viewed for this many days.',
            type: 'integer',
            default: 7,
            options: ['min' => 1, 'max' => 365],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.needs_attention_lead_stuck_days',
            category: 'defaults',
            label: 'Stuck-lead window (days)',
            description: 'The dashboard "Needs attention" tile flags an opportunity as stuck when it has sat in the "contract sent" stage without movement for this many days.',
            type: 'integer',
            default: 14,
            options: ['min' => 1, 'max' => 365],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.dashboard_default_tiles',
            category: 'defaults',
            label: 'Default dashboard tiles (new users)',
            description: 'Which tiles a user lands on before they customize their dashboard. Stored as an ordered, comma-separated list of tile keys; choices come from the tile catalog.',
            type: 'multiselect',
            default: implode(',', DashboardTileRegistry::BUILTIN_DEFAULT_TILES),
            // admin UI sources its choices from the live dashboard-tile
            // catalog (injected by the controller)
            options: ['source' => 'dashboard_tiles'],
        ));
        $this->register(new SettingDefinition(
            key: 'defaults.default_export_template_id',
            category: 'defaults',
            label: 'Default export template',
            description: 'Which export template is used for accounting batches when no template is explicitly selected. Lookup by id; refer to /admin/export-templates.',
            type: 'integer',
        ));

        $this->registerSsoSettings();
        $this->registerMicrosoftSettings();
        $this->registerDocusignSettings();
        $this->registerMeilisearchSettings();
        $this->registerEmailSettings();
        $this->registerPaymentSettings();
    }

    /**
     * Outbound transactional email. A transport selector picks the service;
     * the selected transport's credential fields follow. "default" leaves the
     * env-configured mailer in place.
     */
    protected function registerEmailSettings(): void
    {
        $group = 'email';
        $groupLabel = 'Email delivery';

        $this->register(new SettingDefinition(
            key: 'integrations.mail.transport',
            category: 'integrations',
            label: 'Email transport',
            description: 'Which service delivers outbound mail (receipts, dunning, notifications). "Default" uses the environment-configured driver - typically log in dev. Fill in the credentials for the transport you select.',
            type: 'select',
            default: 'default',
            options: ['choices' => [
                ['value' => 'default', 'label' => 'Default (environment / log)'],
                ['value' => 'postmark', 'label' => 'Postmark'],
                ['value' => 'microsoft365', 'label' => 'Microsoft 365'],
            ]],
            group: $group,
            groupLabel: $groupLabel,
        ));

        // --- Postmark ---
        $this->register(new SettingDefinition(
            key: 'integrations.postmark.token',
            category: 'integrations',
            label: 'Postmark - Server API token',
            description: 'The per-server token from Postmark -> Servers -> API Tokens. Used when the transport is Postmark. Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'POSTMARK_API_KEY',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.postmark.from_email',
            category: 'integrations',
            label: 'Postmark - From address',
            description: 'Verified sender signature in Postmark - must be either an individually verified signature email or sit on a DKIM-confirmed domain. Postmark recommends a real mailbox over no-reply.',
            envKey: 'MAIL_FROM_ADDRESS',
            group: $group,
            groupLabel: $groupLabel,
        ));

        // --- Microsoft 365 (authenticated SMTP to Office 365) ---
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft365.username',
            category: 'integrations',
            label: 'Microsoft 365 - Username (mailbox)',
            description: 'The Microsoft 365 mailbox that authenticates to smtp.office365.com. Used when the transport is Microsoft 365; SMTP AUTH must be enabled for this mailbox in the tenant.',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft365.password',
            category: 'integrations',
            label: 'Microsoft 365 - Password / app password',
            description: 'Password (or app password) for the mailbox above. Encrypted at rest; write-only from this page. Tenants that have retired basic-auth SMTP should use an app password - an OAuth/Microsoft Graph integration is the longer-term path.',
            isSecret: true,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft365.from_email',
            category: 'integrations',
            label: 'Microsoft 365 - From address',
            description: 'The address mail is sent from - usually the same as the mailbox above, or an address that mailbox is permitted to send as.',
            group: $group,
            groupLabel: $groupLabel,
        ));
    }

    /**
     * Payment gateway. A selector picks the active gateway; only its
     * credential fields show (show_when). Switching warns because tokens are
     * gateway-specific. "none" leaves the env default (a safe stub).
     */
    protected function registerPaymentSettings(): void
    {
        $group = 'payments';
        $groupLabel = 'Payment gateway';
        $gatewayKey = 'integrations.payments.gateway';

        $this->register(new SettingDefinition(
            key: $gatewayKey,
            category: 'integrations',
            label: 'Active payment gateway',
            description: 'The single gateway that processes exhibitor and booking payments. "None" leaves the environment default (a safe stub that never charges a live card). Selecting a gateway reveals its credentials below. Only one gateway is active at a time, and tokens stored under one gateway do not work on another.',
            type: 'select',
            default: 'none',
            options: ['choices' => [
                ['value' => 'none', 'label' => 'None (environment / safe stub)'],
                ['value' => 'cardpointe', 'label' => 'CardPointe (Fiserv / CardConnect)'],
                ['value' => 'bluepay', 'label' => 'BluePay (Fiserv, legacy)'],
                ['value' => 'stripe', 'label' => 'Stripe'],
            ], 'warn_on_change' => true],
            group: $group,
            groupLabel: $groupLabel,
        ));

        // --- CardPointe (Fiserv / CardConnect - current Fiserv gateway) ---
        $cardpointeWhen = ['show_when' => ['field' => $gatewayKey, 'value' => 'cardpointe']];
        $this->register(new SettingDefinition(
            key: 'integrations.cardpointe.site',
            category: 'integrations',
            label: 'CardPointe: Site',
            description: 'Your CardConnect site name - the subdomain in your CardPointe URL (for example, "fts" in fts.cardconnect.com). Provided by CardConnect.',
            envKey: 'CARDPOINTE_SITE',
            options: $cardpointeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.cardpointe.merchant_id',
            category: 'integrations',
            label: 'CardPointe: Merchant ID (merchid)',
            description: 'The CardPointe merchant ID for the County\'s merchant account.',
            envKey: 'CARDPOINTE_MERCHANT_ID',
            options: $cardpointeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.cardpointe.username',
            category: 'integrations',
            label: 'CardPointe: API username',
            description: 'The CardPointe Gateway API username used for HTTP Basic authentication.',
            envKey: 'CARDPOINTE_USERNAME',
            options: $cardpointeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.cardpointe.password',
            category: 'integrations',
            label: 'CardPointe: API password',
            description: 'The CardPointe Gateway API password. Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'CARDPOINTE_PASSWORD',
            options: $cardpointeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.cardpointe.environment',
            category: 'integrations',
            label: 'CardPointe: Environment',
            description: 'UAT runs against the CardConnect test sandbox; Production moves funds. Stay on UAT until the merchant account is verified end to end.',
            type: 'select',
            default: 'uat',
            options: ['choices' => [
                ['value' => 'uat', 'label' => 'UAT (sandbox)'],
                ['value' => 'production', 'label' => 'Production'],
            ], 'show_when' => ['field' => $gatewayKey, 'value' => 'cardpointe']],
            group: $group,
            groupLabel: $groupLabel,
        ));

        // --- BluePay (Fiserv BluePay Post / bp10emu) ---
        $bluepayWhen = ['show_when' => ['field' => $gatewayKey, 'value' => 'bluepay']];
        $this->register(new SettingDefinition(
            key: 'integrations.bluepay.merchant_id',
            category: 'integrations',
            label: 'BluePay: Merchant (Account) ID',
            description: 'The 12-digit BluePay Gateway Account ID.',
            envKey: 'BLUEPAY_MERCHANT_ID',
            options: $bluepayWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.bluepay.secret_key',
            category: 'integrations',
            label: 'BluePay: Secret key',
            description: 'The account Secret Key used to compute the TAMPER_PROOF_SEAL. Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'BLUEPAY_SECRET_KEY',
            options: $bluepayWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.bluepay.mode',
            category: 'integrations',
            label: 'BluePay: Mode',
            description: 'TEST runs simulated transactions; LIVE moves funds. Stay on TEST until the merchant account is verified end to end.',
            type: 'select',
            default: 'TEST',
            options: ['choices' => [
                ['value' => 'TEST', 'label' => 'Test'],
                ['value' => 'LIVE', 'label' => 'Live'],
            ], 'show_when' => ['field' => $gatewayKey, 'value' => 'bluepay']],
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.bluepay.hash_type',
            category: 'integrations',
            label: 'BluePay: TPS hash type',
            description: 'The hash algorithm for the TAMPER_PROOF_SEAL. Must match the account\'s "Hash Type in APIs" setting; HMAC_SHA512 is the modern default.',
            type: 'select',
            default: 'HMAC_SHA512',
            options: ['choices' => [
                ['value' => 'HMAC_SHA512', 'label' => 'HMAC_SHA512'],
                ['value' => 'HMAC_SHA256', 'label' => 'HMAC_SHA256'],
                ['value' => 'SHA512', 'label' => 'SHA512'],
                ['value' => 'SHA256', 'label' => 'SHA256'],
                ['value' => 'MD5', 'label' => 'MD5 (legacy)'],
            ], 'show_when' => ['field' => $gatewayKey, 'value' => 'bluepay']],
            group: $group,
            groupLabel: $groupLabel,
        ));

        // --- Stripe ---
        $stripeWhen = ['show_when' => ['field' => $gatewayKey, 'value' => 'stripe']];
        $this->register(new SettingDefinition(
            key: 'integrations.stripe.secret',
            category: 'integrations',
            label: 'Stripe: Secret key',
            description: 'The Stripe secret API key (sk_...). Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'STRIPE_SECRET',
            options: $stripeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.stripe.currency',
            category: 'integrations',
            label: 'Stripe: Currency',
            description: 'ISO currency code for charges (for example, usd).',
            default: 'usd',
            envKey: 'STRIPE_CURRENCY',
            options: $stripeWhen,
            group: $group,
            groupLabel: $groupLabel,
        ));
    }

    /**
     * Meilisearch credentials for the global header search. Self-hosted
     * Meilisearch typically runs on localhost:7700 without a key in
     * dev; production wants a master key and a per-deployment prefix.
     */
    protected function registerMeilisearchSettings(): void
    {
        $group = 'meilisearch';
        $groupLabel = 'Meilisearch (global search)';

        $this->register(new SettingDefinition(
            key: 'integrations.meilisearch.enabled',
            category: 'integrations',
            label: 'Meilisearch enabled',
            description: 'Master toggle for the global header search. When off, the search bar is hidden and Scout falls back to its configured driver.',
            type: 'boolean',
            default: true,
            envKey: 'MEILISEARCH_ENABLED',
            group: $group,
            groupLabel: $groupLabel,
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.meilisearch.host',
            category: 'integrations',
            label: 'Host URL',
            description: 'Meilisearch endpoint, e.g. http://localhost:7700 for a local dev instance or https://your-meili.example.com for a remote deployment.',
            default: 'http://localhost:7700',
            envKey: 'MEILISEARCH_HOST',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.meilisearch.key',
            category: 'integrations',
            label: 'Master key',
            description: 'The Meilisearch master key (or a privileged API key). Encrypted at rest; write-only from this page. Leave blank for local instances running without auth.',
            isSecret: true,
            envKey: 'MEILISEARCH_KEY',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.meilisearch.prefix',
            category: 'integrations',
            label: 'Index prefix',
            description: 'Prepended to every index name so multiple deployments on the same Meilisearch instance don\'t collide. E.g. "velsa_" -> "velsa_bookings".',
            default: 'velsa_',
            envKey: 'SCOUT_PREFIX',
            group: $group,
            groupLabel: $groupLabel,
        ));
    }

    /**
     * SSO meta-settings - the master toggle, provisioning policy,
     * default + bootstrap roles, and the bootstrap-admin email list.
     * Per-provider creds live in their own sections below.
     */
    protected function registerSsoSettings(): void
    {
        $group = 'sso';
        $groupLabel = 'Single sign-on';

        $this->register(new SettingDefinition(
            key: 'integrations.sso.enabled',
            category: 'integrations',
            label: 'SSO enabled',
            description: 'Master toggle for single-sign-on. When off, no SSO buttons appear regardless of per-provider config.',
            type: 'boolean',
            default: false,
            envKey: 'SSO_ENABLED',
            group: $group,
            groupLabel: $groupLabel,
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.sso.provisioning',
            category: 'integrations',
            label: 'Provisioning mode',
            description: '"jit" auto-creates users on first sign-in; "invite_only" refuses sign-ins for users not yet provisioned.',
            default: 'jit',
            envKey: 'SSO_PROVISIONING',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.sso.default_role',
            category: 'integrations',
            label: 'Default role',
            description: 'Role granted to newly-provisioned SSO users (when not on the bootstrap-admin list).',
            default: 'read_only',
            envKey: 'SSO_DEFAULT_ROLE',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.sso.bootstrap_admin_role',
            category: 'integrations',
            label: 'Bootstrap-admin role',
            description: 'Role granted on first sign-in to users whose email appears on the bootstrap-admin list.',
            default: 'super_admin',
            envKey: 'SSO_BOOTSTRAP_ADMIN_ROLE',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.sso.bootstrap_admin_emails',
            category: 'integrations',
            label: 'Bootstrap-admin emails',
            description: 'Comma-separated list of email addresses that receive the bootstrap-admin role on first SSO sign-in.',
            default: '',
            envKey: 'SSO_BOOTSTRAP_ADMIN_EMAILS',
            group: $group,
            groupLabel: $groupLabel,
        ));
    }

    /**
     * Microsoft Entra ID (OIDC) credentials. The Entra portal walkthrough
     * lives in the SSO setup handbook page; these are the values you
     * paste in once the app registration is created.
     */
    protected function registerMicrosoftSettings(): void
    {
        $group = 'microsoft';
        $groupLabel = 'Microsoft Entra ID';

        $this->register(new SettingDefinition(
            key: 'integrations.microsoft.enabled',
            category: 'integrations',
            label: 'Microsoft SSO enabled',
            description: 'Show the "Sign in with Microsoft" button on the login page. Requires the master SSO toggle as well.',
            type: 'boolean',
            default: false,
            envKey: 'SSO_MICROSOFT_ENABLED',
            group: $group,
            groupLabel: $groupLabel,
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft.tenant',
            category: 'integrations',
            label: 'Tenant ID',
            description: 'Directory (tenant) ID from the Entra app-registration overview. Use "common" for multi-tenant.',
            default: 'common',
            envKey: 'MICROSOFT_TENANT_ID',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft.client_id',
            category: 'integrations',
            label: 'Client ID',
            description: 'Application (client) ID from the Entra app-registration overview.',
            envKey: 'MICROSOFT_CLIENT_ID',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft.client_secret',
            category: 'integrations',
            label: 'Client secret',
            description: 'Client-secret Value from Entra. Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'MICROSOFT_CLIENT_SECRET',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.microsoft.redirect',
            category: 'integrations',
            label: 'Redirect URI',
            description: 'Must exactly match the redirect URI registered on the Entra app (scheme, host, path, no trailing slash).',
            envKey: 'MICROSOFT_REDIRECT_URI',
            group: $group,
            groupLabel: $groupLabel,
        ));
    }

    /**
     * DocuSign credentials for the JWT integration grant.
     */
    protected function registerDocusignSettings(): void
    {
        $group = 'docusign';
        $groupLabel = 'DocuSign';

        $this->register(new SettingDefinition(
            key: 'integrations.docusign.enabled',
            category: 'integrations',
            label: 'DocuSign enabled',
            description: 'Swap the signature provider from the demo driver to live DocuSign. Off until credentials below are set + tested.',
            type: 'boolean',
            default: false,
            envKey: 'DOCUSIGN_ENABLED',
            group: $group,
            groupLabel: $groupLabel,
            gatesGroup: true,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.base_uri',
            category: 'integrations',
            label: 'Base URI',
            description: 'The REST endpoint base. Demo tenant defaults to https://demo.docusign.net/restapi.',
            default: 'https://demo.docusign.net/restapi',
            envKey: 'DOCUSIGN_BASE_URI',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.oauth_base',
            category: 'integrations',
            label: 'OAuth base',
            description: 'The OAuth endpoint base. Demo tenant defaults to https://account-d.docusign.com.',
            default: 'https://account-d.docusign.com',
            envKey: 'DOCUSIGN_OAUTH_BASE',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.integration_key',
            category: 'integrations',
            label: 'Integration key',
            description: 'The Integration Key (Client ID) from DocuSign Admin -> Integrations -> Apps. Encrypted at rest.',
            isSecret: true,
            envKey: 'DOCUSIGN_INTEGRATION_KEY',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.secret_key',
            category: 'integrations',
            label: 'Secret key',
            description: 'The Secret Key paired with the Integration Key. Encrypted at rest; write-only from this page.',
            isSecret: true,
            envKey: 'DOCUSIGN_SECRET_KEY',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.connect_hmac_key',
            category: 'integrations',
            label: 'Connect HMAC key',
            description: 'Secret used to verify inbound Connect webhook signatures (DocuSign Admin -> Connect -> HMAC). Separate from the OAuth secret. When set, unsigned or mismatched webhooks are rejected. Required for production. Encrypted at rest; write-only.',
            isSecret: true,
            envKey: 'DOCUSIGN_CONNECT_HMAC_KEY',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.keypair_id',
            category: 'integrations',
            label: 'Keypair ID',
            description: 'The RSA keypair ID from DocuSign for the JWT integration grant.',
            envKey: 'DOCUSIGN_KEYPAIR_ID',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.user_id',
            category: 'integrations',
            label: 'API user ID',
            description: 'The User ID (GUID) DocuSign actions are performed as. Found under DocuSign Admin -> Users.',
            envKey: 'DOCUSIGN_USER_ID',
            group: $group,
            groupLabel: $groupLabel,
        ));
        $this->register(new SettingDefinition(
            key: 'integrations.docusign.account_id',
            category: 'integrations',
            label: 'Account ID',
            description: 'The Account ID (GUID) for the DocuSign tenant. Found under DocuSign Admin -> Account Profile.',
            envKey: 'DOCUSIGN_ACCOUNT_ID',
            group: $group,
            groupLabel: $groupLabel,
        ));
    }
}
