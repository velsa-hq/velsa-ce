<?php

namespace App\Services\WorkOrders;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the assignee for a generated work order from a role name.
 *
 * Queries the Spatie pivot directly: the role() scope is team-context-sensitive
 * and generators run with no active team. Venue-scoped holder wins, else any holder.
 */
class WorkOrderAssigneeResolver
{
    public function resolve(?string $role, ?int $venueId = null): ?int
    {
        if (empty($role)) {
            return null;
        }

        $base = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', $role);

        if ($venueId !== null) {
            $scoped = (clone $base)
                ->where('model_has_roles.venue_id', $venueId)
                ->orderBy('model_has_roles.model_id')
                ->value('model_has_roles.model_id');

            if ($scoped !== null) {
                return (int) $scoped;
            }
        }

        $any = $base
            ->orderBy('model_has_roles.model_id')
            ->value('model_has_roles.model_id');

        return $any !== null ? (int) $any : null;
    }
}
