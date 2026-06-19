<?php

namespace App\Models;

use Database\Factories\LayoutTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable placed-object layout. Scoped to a Space, or global when space_id is null.
 */
class LayoutTemplate extends Model
{
    /** @use HasFactory<LayoutTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'space_id',
        'created_by_user_id',
        'name',
        'category',
        'description',
        'objects_json',
        'object_count',
        'seat_count',
    ];

    protected function casts(): array
    {
        return [
            'objects_json' => 'array',
            'object_count' => 'integer',
            'seat_count' => 'integer',
        ];
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Templates scoped to this space, plus global (space_id null) ones. */
    public function scopeAvailableTo(Builder $query, Space $space): Builder
    {
        return $query->where(function (Builder $q) use ($space) {
            $q->whereNull('space_id')->orWhere('space_id', $space->id);
        });
    }
}
