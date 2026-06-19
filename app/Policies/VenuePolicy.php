<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

/**
 * view -> venues.view; all mutations -> venues.manage. NIST AC-3/AC-6.
 */
class VenuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('venues.view');
    }

    public function view(User $user, Venue $venue): bool
    {
        return $user->hasVenuePermission('venues.view');
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('venues.manage');
    }

    public function update(User $user, Venue $venue): bool
    {
        return $user->hasVenuePermission('venues.manage');
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->hasVenuePermission('venues.manage');
    }

    public function restore(User $user, Venue $venue): bool
    {
        return $user->hasVenuePermission('venues.manage');
    }
}
