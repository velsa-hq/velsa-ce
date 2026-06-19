<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Client authorization, keyed to venue-scoped permissions (hasVenuePermission).
 * NIST AC-3/AC-6: lacking clients.manage blocks mutation even on a reachable route.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('clients.view');
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasVenuePermission('clients.view');
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('clients.manage');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasVenuePermission('clients.manage');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasVenuePermission('clients.manage');
    }

    public function restore(User $user, Client $client): bool
    {
        return $user->hasVenuePermission('clients.manage');
    }
}
