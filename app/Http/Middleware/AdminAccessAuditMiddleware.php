<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs remote-session access to security-relevant admin functions, including
 * read-only browsing that mutates nothing (audit viewer, user/role/permission/SSO
 * screens). Without this, a session browsing security functions leaves no trace
 * beyond the initial login. STIG SRG-APP-000016-AS-000013 (AU-12 / AC-17).
 *
 * The audit-viewer routes are exempt so reading the log does not itself append a
 * recursive admin.access row on every page load.
 */
class AdminAccessAuditMiddleware
{
    /** Route names that must NOT generate an admin.access row (anti-recursion). */
    private const EXEMPT_ROUTES = [
        'admin.audit.index',
        'admin.audit.export',
    ];

    public function __construct(protected AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) $request->route()?->getName();

        if (! in_array($routeName, self::EXEMPT_ROUTES, true)) {
            $this->audit->record('admin.access', payload: [
                'route' => $routeName,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $next($request);
    }
}
