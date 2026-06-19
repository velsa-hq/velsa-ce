<?php

namespace App\Models\Concerns;

use App\Models\Scopes\VenueIsolationScope;

/**
 * Applies the optional VenueIsolationScope to a model with a `venue_id`
 * column. The scope is a no-op unless `operations.venue_isolation` is enabled,
 * so adding this trait changes nothing until an operator turns isolation on.
 */
trait IsVenueScoped
{
    public static function bootIsVenueScoped(): void
    {
        static::addGlobalScope(new VenueIsolationScope);
    }
}
