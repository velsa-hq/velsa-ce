<?php

namespace App\Http\Middleware;

use App\Models\ExhibitorOrder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal IDOR guard (NIST AC-3). Portal order routes are flat (orders/{order});
 * verifies a resolved {order} belongs to the logged-in exhibitor, 404s otherwise.
 * Must run after SubstituteBindings (the param is already a model).
 */
class ScopePortalOrderOwnership
{
    public function handle(Request $request, Closure $next): Response
    {
        $order = $request->route('order');

        if ($order instanceof ExhibitorOrder) {
            $exhibitor = $request->user('exhibitor');
            abort_unless($exhibitor !== null && $order->exhibitor_id === $exhibitor->id, 404);
        }

        return $next($request);
    }
}
