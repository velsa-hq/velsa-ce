<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\AuthorizesVenuePermission;
use App\Support\AdminPermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates `admin.*` routes against the cross-venue permission catalog in
 * AdminPermissionRegistry.
 *
 * Fail-closed: an unmapped route requires `system.settings`, so a new admin
 * route is gated to top admins by default rather than silently open (prevents
 * privilege escalation onto the role/permission screens).
 */
class EnsureAdminPermission
{
    use AuthorizesVenuePermission;

    public function handle(Request $request, Closure $next): Response
    {
        $this->authorizeVenuePermission(
            $request->user(),
            AdminPermissionRegistry::permissionFor(
                (string) $request->route()?->getName(),
                $request->method(),
            ),
        );

        return $next($request);
    }
}
