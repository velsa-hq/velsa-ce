<?php

namespace App\Policies;

use App\Models\ExhibitorEvent;
use App\Models\User;

/**
 * Gated on exhibitors.manage, same as the exhibitors themselves. NIST AC-3/AC-6.
 */
class ExhibitorEventPolicy
{
    public function create(User $user): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }

    public function update(User $user, ExhibitorEvent $event): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }

    public function delete(User $user, ExhibitorEvent $event): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }
}
