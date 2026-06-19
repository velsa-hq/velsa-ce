<?php

namespace App\Services\Sso;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface SsoProvider
{
    // stable identifier used in URLs, config lookups, and audit logs
    public function key(): string;

    public function label(): string;

    public function isEnabled(): bool;

    public function redirect(): RedirectResponse;

    /**
     * Exchange the callback for a SsoIdentity. Implementations must verify
     * signatures and nonces themselves.
     */
    public function handleCallback(): SsoIdentity;
}
