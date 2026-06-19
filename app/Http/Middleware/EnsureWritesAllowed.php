<?php

namespace App\Http\Middleware;

use App\Services\SystemSettings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only mode. When operations.read_only is on, mutating HTTP requests are
 * blocked so the dataset can't change mid-import/maintenance. Blocks
 * interactive writes only; an allowlist keeps auth, the importer, the
 * read-only toggle, and webhooks working. Does not stop queued jobs or
 * scheduled commands.
 */
class EnsureWritesAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBlock($request)) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Velsa is in read-only mode - changes are temporarily disabled.',
            ]);
        }

        return $next($request);
    }

    private function shouldBlock(Request $request): bool
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if (! app(SystemSettings::class)->get('operations.read_only', false)) {
            return false;
        }

        return ! $this->isAllowlisted($request);
    }

    private function isAllowlisted(Request $request): bool
    {
        // server-to-server callbacks aren't interactive writes
        if ($request->is('webhooks/*')) {
            return true;
        }

        $name = (string) ($request->route()?->getName() ?? '');

        // the toggle that turns read-only back off
        if ($name === 'admin.system-settings.update') {
            return true;
        }

        // the importer - read-only is meant to be enabled for an import
        if (str_starts_with($name, 'admin.imports.')) {
            return true;
        }

        // auth essentials (staff + exhibitor portal)
        return in_array($name, ['login', 'logout', 'portal.logout'], true)
            || str_starts_with($name, 'password.')
            || str_starts_with($name, 'two-factor.');
    }
}
