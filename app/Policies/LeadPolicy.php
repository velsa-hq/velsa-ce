<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

/**
 * Mutations (including pipeline state/activities) gated on leads.manage. NIST AC-3/AC-6.
 */
class LeadPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasVenuePermission('leads.view');
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->permits($user, 'leads.view', $lead->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->hasVenuePermission('leads.manage');
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->permits($user, 'leads.manage', $lead->venue_id);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $this->permits($user, 'leads.manage', $lead->venue_id);
    }
}
