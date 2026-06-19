<?php

namespace App\Services\Sso;

use RuntimeException;

class SsoRegistry
{
    /** @var array<string, SsoProvider> */
    protected array $providers = [];

    public function register(SsoProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): SsoProvider
    {
        if (! isset($this->providers[$key])) {
            throw new RuntimeException("No SSO provider registered for '{$key}'.");
        }

        return $this->providers[$key];
    }

    /**
     * Providers that have credentials configured and are user-visible.
     *
     * @return array<string, SsoProvider>
     */
    public function enabled(): array
    {
        return array_filter(
            $this->providers,
            fn (SsoProvider $p) => $p->isEnabled(),
        );
    }

    public function anyEnabled(): bool
    {
        return ! empty($this->enabled());
    }
}
