<?php

namespace App\Models;

use App\Models\Concerns\IsVenueScoped;
use Database\Factories\ResourceInventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class ResourceInventory extends Model
{
    /** @use HasFactory<ResourceInventoryFactory> */
    use HasFactory, IsVenueScoped, SoftDeletes;

    const DELETED_AT = 'retired_at';

    protected $table = 'resource_inventories';

    protected $fillable = [
        'venue_id',
        'kind',
        'sku',
        'name',
        'quantity_total',
        'quantity_available',
        'reorder_point',
        'is_consumable',
        'attributes_json',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'quantity_available' => 'integer',
            'reorder_point' => 'integer',
            'is_consumable' => 'boolean',
            'attributes_json' => 'array',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // can't retire while stock is still applied to open work orders
        static::deleting(function (ResourceInventory $resource): void {
            $applied = WorkOrderItem::query()
                ->where('resource_inventory_id', $resource->id)
                ->whereNotNull('applied_at')
                ->exists();

            if ($applied) {
                throw new RuntimeException(
                    "Cannot retire '{$resource->name}' - it has inventory applied to open work orders. Complete or reverse those first.",
                );
            }
        });
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
