<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO (Section 8 - Authentication)
    |--------------------------------------------------------------------------
    |
    | Configures single-sign-on for staff users. Today only the Microsoft
    | (Entra ID) provider is wired; the structure leaves room for Google,
    | SAML, or Duo Universal Prompt to drop in alongside without touching
    | the controller or login view. Credentials live in .env (gitignored)
    | and are never committed to the repo - same rule as DocuSign/BluePay.
    |
    */

    'enabled' => env('SSO_ENABLED', false),

    /*
    | When a user successfully signs in via SSO but no matching User row
    | exists, what should happen? Two modes:
    |
    |  - "jit"           Auto-provision the user. First sign-in from
    |                    a bootstrap_admin_emails address grants the
    |                    bootstrap_admin_role; everyone else gets
    |                    default_role.
    |  - "invite_only"   Reject the sign-in with a flash error. Admin
    |                    must invite the user first (creating a User
    |                    row with email set; SSO then matches on email
    |                    and stamps the entra_oid on first sign-in).
    |
    | Enterprise deployments usually want invite_only for least-
    | privilege; "jit" is the more flexible default and works well for
    | POCs and small-team installations.
    */
    'provisioning' => env('SSO_PROVISIONING', 'jit'),

    'default_role' => env('SSO_DEFAULT_ROLE', 'read_only'),
    'bootstrap_admin_role' => env('SSO_BOOTSTRAP_ADMIN_ROLE', 'super_admin'),

    /*
    | Comma-separated list of email addresses that automatically receive
    | the 'admin' role on first JIT provision. Useful for bootstrap: when
    | the very first user signs in via SSO they need an admin role to
    | configure the system, but there's no one to invite them yet.
    */
    'bootstrap_admin_emails' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SSO_BOOTSTRAP_ADMIN_EMAILS', '')),
    )),

    'providers' => [

        'microsoft' => [
            'enabled' => env('SSO_MICROSOFT_ENABLED', false),
            'label' => 'Sign in with Microsoft',
            'tenant' => env('MICROSOFT_TENANT_ID', 'common'),
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'redirect' => env('MICROSOFT_REDIRECT_URI'),
            // GroupMember.Read.All powers the Entra-group -> role
            // mapping logic in EntraGroupsClient. It requires admin
            // consent on the Entra app registration. If consent
            // isn't granted, MS Graph returns 403 and the group
            // resolver no-ops gracefully (sign-in still succeeds, no
            // mapped roles get applied).
            'scopes' => ['openid', 'profile', 'email', 'User.Read', 'GroupMember.Read.All'],
        ],

    ],

];
