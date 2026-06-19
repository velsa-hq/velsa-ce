<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\AuthorizesVenuePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on a global (cross-venue) permission, for org-wide actions
 * not scoped to one venue (e.g. posting a manual journal entry).
 *
 * Usage: ->middleware(EnsureGlobalPermission::class.':accounting.post_journal')
 */
class EnsureGlobalPermission
{
    use AuthorizesVenuePermission;

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $this->authorizeVenuePermission($request->user(), $permission);

        return $next($request);
    }
}
