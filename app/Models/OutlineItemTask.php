<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tickable checklist task on a run-of-show outline item. Ordered by
 * `position`; `is_done` (+ `done_at`) let day-of ops check items off.
 */
class OutlineItemTask extends Model
{
    protected $fillable = [
        'outline_item_id',
        'label',
        'position',
        'is_done',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_done' => 'boolean',
            'done_at' => 'datetime',
        ];
    }

    public function outlineItem(): BelongsTo
    {
        return $this->belongsTo(OutlineItem::class);
    }
}
