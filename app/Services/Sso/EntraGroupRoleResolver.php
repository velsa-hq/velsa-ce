<?php

namespace App\Services\Sso;

use App\Models\EntraGroupRoleMapping;
use App\Models\User;
use App\Models\Venue;
use Spatie\Permission\Models\Role;

/**
 * Applies Entra-group-driven role assignments after SSO sign-in.
 * Additive only: assigns roles the user's groups grant but does not
 * revoke on group removal. A null mapping venue_id means every active
 * venue.
 */
class EntraGroupRoleResolver
{
    /**
     * @param  list<string>  $entraGroupIds  Group GUIDs from MS Graph
     * @return list<array{role:string,venue_id:int,via_group:string}> what we applied
     */
    public function applyForUser(User $user, array $entraGroupIds): array
    {
        if ($entraGroupIds === []) {
            return [];
        }

        $mappings = EntraGroupRoleMapping::query()
            ->forEntraGroups($entraGroupIds)
            ->get();
        if ($mappings->isEmpty()) {
            return [];
        }

        $validRoles = Role::query()->pluck('name')->all();

        $applied = [];
        foreach ($mappings as $mapping) {
            // skip mappings whose role was deleted without cleanup
            if (! in_array($mapping->role_name, $validRoles, true)) {
                continue;
            }

            $venues = $mapping->venue_id === null
                ? Venue::query()->active()->get()
                : Venue::query()->whereKey($mapping->venue_id)->get();

            foreach ($venues as $venue) {
                $user->assignRoleAt($venue, $mapping->role_name);
                $applied[] = [
                    'role' => $mapping->role_name,
                    'venue_id' => $venue->id,
                    'via_group' => $mapping->entra_group_id,
                ];
            }
        }

        return $applied;
    }
}
