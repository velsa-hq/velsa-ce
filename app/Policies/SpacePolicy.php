<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

/**
 * Mutations gated on spaces.manage. NIST AC-3/AC-6.
 */
class SpacePolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('spaces.view');
    }

    public function view(User $user, Space $space): bool
    {
        return $this->permits($user, 'spaces.view', $space->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('spaces.manage');
    }

    public function update(User $user, Space $space): bool
    {
        return $this->permits($user, 'spaces.manage', $space->venue_id);
    }

    public function delete(User $user, Space $space): bool
    {
        return $this->permits($user, 'spaces.manage', $space->venue_id);
    }
}
