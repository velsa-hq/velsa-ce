<?php

namespace App\Services\Sso;

/**
 * Provider-neutral SSO claims, so the resolver works the same across providers.
 */
class SsoIdentity
{
    public function __construct(
        public string $provider,
        public string $providerUserId,
        public string $email,
        public ?string $name = null,
        /** @var array<string, mixed> */
        public array $rawClaims = [],
        // access token for post-sign-in calls (e.g. Entra group lookup via MS Graph)
        public ?string $accessToken = null,
    ) {}
}
