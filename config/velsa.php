<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safe mode ("Demo" / "Training")
    |--------------------------------------------------------------------------
    |
    | When on, the instance is feature-complete but externally inert: every
    | outbound side-effect is neutralized. E-signature and payment drivers are
    | forced to their Fakes, and outbound mail is redirected to one address or
    | suppressed (logged, never sent). One switch behind the public demo, a
    | customer training/sandbox, and any UAT environment. Enforced server-side
    | (service container + mailer), not the UI. Orthogonal to the seed dataset.
    |
    */

    'safe_mode' => (bool) env('VELSA_SAFE_MODE', false),

    // banner label shown when safe mode is on (e.g. "DEMO", "TRAINING", "UAT")
    'safe_mode_label' => env('VELSA_SAFE_MODE_LABEL', 'DEMO'),

    // where safe-mode mail goes: one address redirects all mail there; null
    // routes mail to the log channel
    'safe_mode_mail_to' => env('VELSA_SAFE_MODE_MAIL_TO'),

    /*
    |--------------------------------------------------------------------------
    | Strict models (N+1 surfacing)
    |--------------------------------------------------------------------------
    |
    | When on, Eloquent throws on lazy-loaded relations so N+1s surface as
    | test failures. Opt-in (default off) so the normal suite stays green;
    | run the suite with VELSA_STRICT_MODELS=1 to hunt N+1s, fix the
    | hotspots, then we can flip the default on in non-prod. Never on in
    | production.
    |
    */

    'strict_models' => (bool) env('VELSA_STRICT_MODELS', false),

    /*
    |--------------------------------------------------------------------------
    | Scheduled-report egress (data-exfiltration control, NIST AC-4)
    |--------------------------------------------------------------------------
    |
    | A scheduled report emails sensitive financial/PII data to recipients on a
    | recurring basis: a standing egress channel. `report_recipient_domains` is
    | an optional allowlist of recipient domains (comma-separated). When empty
    | (default) any well-formed address is allowed, but every schedule and
    | dispatch is audited. `report_max_recipients` caps fan-out.
    |
    */

    'report_recipient_domains' => array_filter(array_map(
        'trim',
        explode(',', (string) env('VELSA_REPORT_RECIPIENT_DOMAINS', '')),
    )),

    'report_max_recipients' => (int) env('VELSA_REPORT_MAX_RECIPIENTS', 20),

    /*
    |--------------------------------------------------------------------------
    | Multi-factor authentication for privileged accounts (NIST IA-2(1))
    |--------------------------------------------------------------------------
    |
    | When on, the admin area requires an MFA-backed session: SSO (the IdP did
    | MFA), an enrolled TOTP authenticator, or a registered passkey
    | (phishing-resistant, preferred). A privileged user without any is
    | redirected to set one up.
    |
    | Secure by default in production; off elsewhere so the demo, local dev, and
    | un-provisioned environments aren't locked out. Safe mode bypasses it.
    | Force either way with VELSA_REQUIRE_ADMIN_MFA.
    |
    */

    'require_admin_mfa' => (bool) env('VELSA_REQUIRE_ADMIN_MFA', env('APP_ENV') === 'production'),

];
