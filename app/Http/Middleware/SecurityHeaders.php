<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth response headers (STIG APSC-DV-002490 / NIST SI-10, SC-8).
 *
 * CSP uses a nonce-based script-src: only the per-request nonce (minted in
 * AppServiceProvider via Vite::useCspNonce()) and scripts it loads
 * ('strict-dynamic') may run, blocking injected inline scripts. 'strict-dynamic'
 * holds in both prod (chunk imports) and dev (cross-origin HMR) without an
 * origin allowlist. style-src is left open on purpose: React/Tailwind inject
 * inline styles heavily, so a strict style policy is high-breakage / low-value.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $nonce = Vite::cspNonce();
        $scriptSrc = $nonce === null
            ? "script-src 'self'"
            : "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic'";

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "{$scriptSrc}; frame-ancestors 'none'; base-uri 'self'; object-src 'none'",
        ];

        // HSTS only over HTTPS; never emit it over plain HTTP
        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $key => $value) {
            // don't clobber a header a downstream layer set
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
