<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SafeMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require MFA for admin areas (NIST IA-2(1) / STIG SRG-APP-000149).
 * MFA-backed = SSO (IdP did MFA), a confirmed passkey, or confirmed TOTP
 * (Fortify enforces the TOTP challenge at login, so a confirmed enrollment
 * means this session already passed it). Gated by config and skipped in safe
 * mode so the demo isn't locked out.
 */
class EnsurePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('velsa.require_admin_mfa', false) || SafeMode::enabled()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User && $this->isMfaBacked($user)) {
            return $next($request);
        }

        return redirect()->route('security.edit')->with('toast', [
            'type' => 'error',
            'message' => 'Administrative areas require multi-factor authentication. '
                .'Add a passkey or an authenticator app, then try again.',
        ]);
    }

    private function isMfaBacked(User $user): bool
    {
        return $user->sso_provider !== null
            || $user->two_factor_confirmed_at !== null
            || $user->passkeys()->exists();
    }
}
