<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-user idle timeout (default 15 min, config auth.idle_timeout_minutes).
 * last_active_at is bumped by UpdateLastActiveMiddleware so the timeout check
 * runs before the timestamp ticks forward.
 */
class IdleTimeoutMiddleware
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // staff Users only; exhibitor magic-link sessions have their own expiry
        if ($user instanceof User) {
            $timeoutMinutes = (int) config('auth.idle_timeout_minutes', 15);
            $expired = $user->last_active_at !== null
                && $user->last_active_at->diffInMinutes(now()) >= $timeoutMinutes;

            $forced = $user->force_logout_at !== null
                && $user->force_logout_at->isPast();

            // disabled mid-session signs out on the next request, not at timeout
            $disabled = $user->isDisabled();

            if ($expired || $forced || $disabled) {
                // audit the involuntary session end (AU-12 / APSC-DV-000660)
                // before logout, while the actor is still known
                $this->audit->record(
                    eventType: 'session.timeout',
                    user: $user,
                    payload: ['reason' => $expired ? 'idle_timeout' : ($disabled ? 'account_disabled' : 'forced_logout')],
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Inertia needs location() redirect; JSON would trip the client
                if ($request->header('X-Inertia')) {
                    return Inertia::location(route('login'));
                }

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired due to inactivity.'], 419);
                }

                return redirect()->route('login')->with('status', 'Signed out after inactivity.');
            }
        }

        return $next($request);
    }
}
