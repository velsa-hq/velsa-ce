<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared scopes for user-definable lookup taxonomies (space kinds, ops
 * departments, event kinds, ...). Tables share the `taxonomyColumns()`
 * Blueprint macro; models declare their own $fillable + casts.
 */
trait IsTaxonomy
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
