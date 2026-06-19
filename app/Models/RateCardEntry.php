<?php

namespace App\Models;

use App\Enums\BookableUnit;
use Database\Factories\RateCardEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $rate_card_id
 * @property int|null $space_id
 * @property string|null $equipment_sku
 * @property BookableUnit $unit
 * @property int $rate_cents
 * @property int $min_charge_cents
 * @property int|null $included_hours
 * @property array<string, mixed>|null $conditions_json
 */
class RateCardEntry extends Model
{
    /** @use HasFactory<RateCardEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'rate_card_id',
        'space_id',
        'equipment_sku',
        'unit',
        'rate_cents',
        'min_charge_cents',
        'included_hours',
        'conditions_json',
    ];

    protected function casts(): array
    {
        return [
            'unit' => BookableUnit::class,
            'rate_cents' => 'integer',
            'min_charge_cents' => 'integer',
            'included_hours' => 'integer',
            'conditions_json' => 'array',
        ];
    }

    /** @return BelongsTo<RateCard, $this> */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(RateCard::class);
    }

    /** @return BelongsTo<Space, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
