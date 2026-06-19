<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsTaxonomy;
use Database\Factories\InventoryKindFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User-definable taxonomy of inventory kinds (chairs, tables, av, ...).
 * Resources store the kind as the `key` slug in resource_inventories.kind
 * (soft reference) so the column survives a rename or retirement.
 *
 * @see IsTaxonomy
 */
class InventoryKind extends Model
{
    /** @use HasFactory<InventoryKindFactory> */
    use Auditable, HasFactory, IsTaxonomy;

    protected $fillable = [
        'key',
        'label',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    // soft reference: resource_inventories.kind holds the `key` slug
    public function resources(): HasMany
    {
        return $this->hasMany(ResourceInventory::class, 'kind', 'key');
    }
}
