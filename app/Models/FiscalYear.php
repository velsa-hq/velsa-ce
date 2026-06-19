<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FiscalYearFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FiscalYear extends Model
{
    /** @use HasFactory<FiscalYearFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'label',
        'starts_on',
        'ends_on',
        'is_closed',
        'closed_at',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_closed' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'label';
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_closed', false);
    }

    /** Null when no defined year covers the date - flags a gap rather than posting to the wrong year. */
    public static function forDate(\DateTimeInterface $date): ?self
    {
        return static::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    public function contains(\DateTimeInterface $date): bool
    {
        return $date >= $this->starts_on && $date <= $this->ends_on;
    }
}
