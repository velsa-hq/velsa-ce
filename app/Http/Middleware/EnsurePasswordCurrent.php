<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a password change before any other route when the password has
 * exceeded its max lifetime (APSC-DV-001770) or an admin issued a temporary
 * one (force_password_change, APSC-DV-001790).
 *
 * The password page, its update submit, and logout are exempt so the user
 * isn't deadlocked. No-op unless max_age_days > 0 or the flag is set.
 */
class EnsurePasswordCurrent
{
    /** routes reachable while a change is pending */
    private const EXEMPT = ['security.edit', 'user-password.update', 'logout'];

    public function __construct(private readonly PasswordPolicy $policy) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User
            && $this->policy->mustChange($user)
            && ! in_array($request->route()?->getName(), self::EXEMPT, true)) {
            $message = __('Your password must be changed before you can continue.');

            if ($request->header('X-Inertia')) {
                return Inertia::location(route('security.edit'));
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 423);
            }

            return redirect()->route('security.edit')->with('status', $message);
        }

        return $next($request);
    }
}
