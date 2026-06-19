<?php

namespace App\Http\Middleware\Concerns;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Shared fail-closed 403 check on a global (cross-venue) permission,
 * the union a user holds across every venue (User::hasVenuePermission).
 */
trait AuthorizesVenuePermission
{
    protected function authorizeVenuePermission(?Authenticatable $user, string $permission): void
    {
        abort_unless($user instanceof User && $user->hasVenuePermission($permission), 403);
    }
}
