<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DocuSign - e-signature integration
    |--------------------------------------------------------------------------
    |
    | Toggle DOCUSIGN_ENABLED to swap the SignatureProvider binding from
    | FakeSignatureProvider to the real DocuSignSignatureProvider (when
    | implemented). Credentials are loaded from env so they never touch
    | the repo; the PEM key pair lives under storage/app/keys/ which is
    | gitignored. Sandbox base URIs default to the demo tenant.
    |
    */

    'docusign' => [
        'enabled' => env('DOCUSIGN_ENABLED', false),
        'base_uri' => env('DOCUSIGN_BASE_URI', 'https://demo.docusign.net/restapi'),
        'oauth_base' => env('DOCUSIGN_OAUTH_BASE', 'https://account-d.docusign.com'),
        'integration_key' => env('DOCUSIGN_INTEGRATION_KEY'),
        'secret_key' => env('DOCUSIGN_SECRET_KEY'),
        'keypair_id' => env('DOCUSIGN_KEYPAIR_ID'),
        'private_key_path' => env('DOCUSIGN_PRIVATE_KEY_PATH', 'storage/app/keys/docusign-private.pem'),
        'public_key_path' => env('DOCUSIGN_PUBLIC_KEY_PATH', 'storage/app/keys/docusign-public.pem'),
        'user_id' => env('DOCUSIGN_USER_ID'),
        'account_id' => env('DOCUSIGN_ACCOUNT_ID'),
    ],

];
