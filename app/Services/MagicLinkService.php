<?php

namespace App\Services;

use App\Models\Exhibitor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues + verifies short-lived magic-link tokens for the exhibitor portal.
 * Only the token hash is persisted; the plaintext is returned once from issue().
 * TTL defaults to 3 days, overridable via security.portal_magic_link_ttl_days.
 */
class MagicLinkService
{
    public const DEFAULT_TTL_DAYS = 3;

    // returns plaintext token; caller embeds it in the URL and must not re-store it
    public function issue(Exhibitor $exhibitor, int $ttlDays = self::DEFAULT_TTL_DAYS): string
    {
        $plain = Str::random(64);

        $exhibitor->forceFill([
            'magic_token' => Hash::make($plain),
            'magic_token_expires_at' => now()->addDays($ttlDays),
        ])->save();

        return $plain;
    }

    // constant-time hash check; null on no match, expiry, or deleted exhibitor
    public function verify(string $plain): ?Exhibitor
    {
        $candidates = Exhibitor::query()
            ->whereNotNull('magic_token')
            ->where('magic_token_expires_at', '>', now())
            ->get();

        foreach ($candidates as $exhibitor) {
            if (Hash::check($plain, (string) $exhibitor->magic_token)) {
                return $exhibitor;
            }
        }

        return null;
    }

    // one-time-use: clear the token after login or on logout
    public function consume(Exhibitor $exhibitor): void
    {
        $exhibitor->forceFill([
            'magic_token' => null,
            'magic_token_expires_at' => null,
        ])->save();
    }

    // relative so the controller decides absolute vs signed
    public function loginUrl(string $plainToken): string
    {
        return '/portal/login/'.$plainToken;
    }
}
