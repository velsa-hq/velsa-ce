<?php

namespace App\Models;

use App\Enums\RateCardKind;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsVenueScoped;
use Carbon\CarbonImmutable;
use Database\Factories\RateCardFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $venue_id
 * @property string $name
 * @property RateCardKind $kind
 * @property string $currency
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property bool $is_active
 * @property string|null $notes
 */
class RateCard extends Model
{
    /** @use HasFactory<RateCardFactory> */
    use Auditable, HasFactory, IsVenueScoped;

    protected $fillable = [
        'venue_id',
        'name',
        'kind',
        'currency',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => RateCardKind::class,
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

    /** @return HasMany<RateCardEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(RateCardEntry::class);
    }

    public function scopeEffectiveOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('is_active', true)
            ->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }
}
