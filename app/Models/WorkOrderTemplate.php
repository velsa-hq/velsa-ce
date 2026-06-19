<?php

namespace App\Models;

use App\Enums\WorkOrderKind;
use App\Models\Concerns\IsVenueScoped;
use Database\Factories\WorkOrderTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderTemplate extends Model
{
    /** @use HasFactory<WorkOrderTemplateFactory> */
    use HasFactory, IsVenueScoped;

    protected $fillable = [
        'venue_id',
        'name',
        'kind',
        'recurrence_rrule',
        'items_json',
        'default_assignee_role',
        'lookahead_days',
        'last_materialized_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => WorkOrderKind::class,
            'items_json' => 'array',
            'lookahead_days' => 'integer',
            'last_materialized_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'template_id');
    }

    public function scopeActiveRecurring(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('recurrence_rrule');
    }
}
