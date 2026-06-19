<?php

namespace App\Models\Scopes;

use App\Models\User;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Optional per-venue data isolation. When `operations.venue_isolation` is on,
 * venue-scoped models only return rows for venues the user has a role at.
 *
 * Bypassed when the setting is off (default), for `venues.view_all` holders,
 * and when there is no authenticated user (CLI/queue/seeders must not be filtered).
 */
class VenueIsolationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app(SystemSettings::class)->get('operations.venue_isolation', false)) {
            return;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        if ($user->hasVenuePermission('venues.view_all')) {
            return;
        }

        // null venue_id = org-level rows, visible to everyone; only venue-bound rows filter
        $builder->where(function (Builder $inner) use ($model, $user): void {
            $inner->whereIn($model->getTable().'.venue_id', $user->accessibleVenueIds())
                ->orWhereNull($model->getTable().'.venue_id');
        });
    }
}
