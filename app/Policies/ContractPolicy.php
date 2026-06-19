<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

/**
 * No separate delete permission: authoring rights (contracts.create) govern the
 * full void/destroy/restore lifecycle. NIST AC-3/AC-6.
 */
class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('contracts.view');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasVenuePermission('contracts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('contracts.create');
    }

    public function send(User $user, Contract $contract): bool
    {
        return $user->hasVenuePermission('contracts.send');
    }

    public function manage(User $user, Contract $contract): bool
    {
        return $user->hasVenuePermission('contracts.create');
    }
}
