<?php

namespace App\Services\Sso;

use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Microsoft Entra ID SSO via OIDC code-flow, backed by Socialite +
 * socialiteproviders/microsoft-azure.
 *
 * Joins on the object identifier (oid), persisted to User.entra_oid:
 * emails change, oid does not.
 */
class MicrosoftEntraProvider implements SsoProvider
{
    public function __construct(
        protected Socialite $socialite,
    ) {}

    public function key(): string
    {
        return 'microsoft';
    }

    public function label(): string
    {
        return (string) config('sso.providers.microsoft.label', 'Sign in with Microsoft');
    }

    public function isEnabled(): bool
    {
        if (! config('sso.enabled')) {
            return false;
        }
        if (! config('sso.providers.microsoft.enabled')) {
            return false;
        }

        return config('sso.providers.microsoft.client_id') !== null
            && config('sso.providers.microsoft.client_secret') !== null;
    }

    public function redirect(): RedirectResponse
    {
        /** @var AbstractProvider $driver */
        $driver = $this->socialite->driver('microsoft-azure');

        return $driver
            ->scopes((array) config('sso.providers.microsoft.scopes', []))
            ->redirect();
    }

    public function handleCallback(): SsoIdentity
    {
        $user = $this->socialite->driver('microsoft-azure')->user();

        $oid = (string) $user->getId();
        $email = (string) $user->getEmail();

        if ($oid === '') {
            throw new RuntimeException('Entra returned no object identifier (oid).');
        }
        if ($email === '') {
            throw new RuntimeException('Entra returned no email/UPN claim.');
        }

        return new SsoIdentity(
            provider: $this->key(),
            providerUserId: $oid,
            email: $email,
            name: $user->getName(),
            rawClaims: (array) ($user->user ?? []),
            accessToken: $user->token ?? null,
        );
    }
}
