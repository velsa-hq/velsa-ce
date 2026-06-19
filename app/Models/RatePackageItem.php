<?php

namespace App\Models;

use App\Enums\BookableUnit;
use Database\Factories\RatePackageItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A package line: space, catalogue equipment, or free-text service plus quantity.
 * Priced at the package level - these only document the bundle's contents.
 *
 * @property int $id
 * @property int $rate_package_id
 * @property int|null $space_id
 * @property string|null $equipment_sku
 * @property string|null $label
 * @property int $quantity
 * @property BookableUnit|null $unit
 * @property string|null $notes
 */
class RatePackageItem extends Model
{
    /** @use HasFactory<RatePackageItemFactory> */
    use HasFactory;

    protected $fillable = [
        'rate_package_id',
        'space_id',
        'equipment_sku',
        'label',
        'quantity',
        'unit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit' => BookableUnit::class,
        ];
    }

    /** @return BelongsTo<RatePackage, $this> */
    public function ratePackage(): BelongsTo
    {
        return $this->belongsTo(RatePackage::class);
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
