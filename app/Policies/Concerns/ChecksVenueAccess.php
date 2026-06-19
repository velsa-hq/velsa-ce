<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Venue;
use App\Services\SystemSettings\SystemSettings;

/**
 * Defense-in-depth for venue-scoped policies. When isolation is on, also require
 * the permission at the record's own venue (canAt) so cross-venue access is
 * blocked at the authz layer, not only by VenueIsolationScope at the query layer.
 * Shares the same setting + venues.view_all bypass so the two layers stay in sync.
 */
trait ChecksVenueAccess
{
    protected function permits(User $user, string $permission, ?int $venueId): bool
    {
        if (
            $venueId === null
            || ! app(SystemSettings::class)->get('operations.venue_isolation', false)
            || $user->hasVenuePermission('venues.view_all')
        ) {
            return $user->hasVenuePermission($permission);
        }

        $venue = Venue::find($venueId);

        return $venue !== null && $user->canAt($venue, $permission);
    }
}
