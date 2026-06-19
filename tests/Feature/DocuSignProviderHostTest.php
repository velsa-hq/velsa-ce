<?php

use App\Services\Signing\DocuSignSignatureProvider;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// the SDK OAuth helper does not inherit the REST host: without an explicit
// base path the JWT grant hits production and a demo key fails with
// issuer_not_found. buildApiClient() must pin the OAuth host to oauth_base.
it('pins the JWT OAuth host to the configured demo tenant', function () {
    app(SystemSettings::class)->set('integrations.docusign.base_uri', 'https://demo.docusign.net/restapi');
    app(SystemSettings::class)->set('integrations.docusign.oauth_base', 'https://account-d.docusign.com');

    $provider = new DocuSignSignatureProvider(app(SystemSettings::class));
    $client = (fn () => $this->buildApiClient())->call($provider);

    expect($client->getOAuth()->getOAuthBasePath())->toBe('account-d.docusign.com');
});

it('pins the JWT OAuth host to production when configured for production', function () {
    app(SystemSettings::class)->set('integrations.docusign.base_uri', 'https://www.docusign.net/restapi');
    app(SystemSettings::class)->set('integrations.docusign.oauth_base', 'https://account.docusign.com');

    $provider = new DocuSignSignatureProvider(app(SystemSettings::class));
    $client = (fn () => $this->buildApiClient())->call($provider);

    expect($client->getOAuth()->getOAuthBasePath())->toBe('account.docusign.com');
});
