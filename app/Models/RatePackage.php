<?php

namespace App\Models;

use App\Enums\RateCardKind;
use App\Models\Concerns\IsVenueScoped;
use Carbon\CarbonImmutable;
use Database\Factories\RatePackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bundle sold at a single price covering multiple components
 * (RatePackageItem). Venue-scoped and effective-dated, mirroring RateCard.
 *
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property RateCardKind $kind
 * @property string $currency
 * @property int $price_cents
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property bool $is_active
 * @property string|null $description
 */
class RatePackage extends Model
{
    /** @use HasFactory<RatePackageFactory> */
    use HasFactory, IsVenueScoped;

    protected $fillable = [
        'venue_id',
        'name',
        'kind',
        'currency',
        'price_cents',
        'effective_from',
        'effective_to',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RateCardKind::class,
            'price_cents' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<RatePackageItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RatePackageItem::class);
    }

    /**
     * @param  Builder<RatePackage>  $query
     * @return Builder<RatePackage>
     */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
