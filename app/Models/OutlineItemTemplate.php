<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\OutlineItemTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Reusable run-of-show item; applying it spawns an OutlineItem plus
 * checklist tasks from these defaults.
 *
 * `checklist` is an ordered array of task labels, materialized into
 * outline_item_tasks rows on use. `is_system` rows are seeded defaults
 * and protected from deletion.
 */
class OutlineItemTemplate extends Model
{
    /** @use HasFactory<OutlineItemTemplateFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'label',
        'department',
        'default_duration_minutes',
        'description',
        'checklist',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'default_duration_minutes' => 'integer',
            'checklist' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
