<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bumps last_active_at, coalesced to at most once per 60s. Must run after
 * IdleTimeoutMiddleware so an about-to-expire session isn't refreshed. Only
 * internal User accounts have this column; other guards are skipped.
 */
class UpdateLastActiveMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $shouldBump = $user->last_active_at === null
                || $user->last_active_at->diffInSeconds(now()) >= 60;

            if ($shouldBump) {
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
