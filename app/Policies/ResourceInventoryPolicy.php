<?php

namespace App\Policies;

use App\Models\ResourceInventory;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

/**
 * No dedicated inventory.* permission yet, so gated on workorders.manage (work
 * orders consume this stock). NIST AC-3/AC-6.
 */
class ResourceInventoryPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('workorders.view');
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('workorders.manage');
    }

    public function update(User $user, ResourceInventory $resourceInventory): bool
    {
        return $this->permits($user, 'workorders.manage', $resourceInventory->venue_id);
    }

    public function delete(User $user, ResourceInventory $resourceInventory): bool
    {
        return $this->permits($user, 'workorders.manage', $resourceInventory->venue_id);
    }
}
