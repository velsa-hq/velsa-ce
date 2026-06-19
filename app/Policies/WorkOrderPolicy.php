<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Concerns\ChecksVenueAccess;

/**
 * view -> workorders.view; mutations -> workorders.manage; status changes
 * -> workorders.complete. NIST AC-3/AC-6.
 */
class WorkOrderPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('workorders.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $this->permits($user, 'workorders.view', $workOrder->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('workorders.manage');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $this->permits($user, 'workorders.manage', $workOrder->venue_id);
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $this->permits($user, 'workorders.manage', $workOrder->venue_id);
    }

    public function complete(User $user, WorkOrder $workOrder): bool
    {
        return $this->permits($user, 'workorders.complete', $workOrder->venue_id);
    }
}
