<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntraGroupRoleMapping extends Model
{
    protected $fillable = [
        'entra_group_id',
        'group_label',
        'role_name',
        'venue_id',
        'created_by_user_id',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @param  list<string>  $entraGroupIds
     */
    public function scopeForEntraGroups(Builder $query, array $entraGroupIds): Builder
    {
        return $query->whereIn('entra_group_id', $entraGroupIds);
    }
}
