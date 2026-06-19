<?php

namespace App\Policies;

use App\Models\Exhibitor;
use App\Models\User;

/**
 * Staff-side only (exhibitor portal is a separate guard). No exhibitors.view
 * permission: all admin gated on exhibitors.manage. Order payment actions also
 * need payments.* permissions, checked in the controller. NIST AC-3/AC-6.
 */
class ExhibitorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }

    public function view(User $user, Exhibitor $exhibitor): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }

    public function manage(User $user, ?Exhibitor $exhibitor = null): bool
    {
        return $user->hasVenuePermission('exhibitors.manage');
    }
}
