<?php

namespace App\Models;

use App\Enums\FundType;
use App\Models\Concerns\Auditable;
use Database\Factories\FundFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    /** @use HasFactory<FundFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'fund_type',
        'description',
        'parent_fund_id',
        'active_from',
        'active_to',
    ];

    protected function casts(): array
    {
        return [
            'fund_type' => FundType::class,
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_fund_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_fund_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /** Funds inside their active window; NULL active_from/active_to means open-ended. */
    public function scopeActive(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        $on = $on ? $on->format('Y-m-d') : now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('active_from')->orWhere('active_from', '<=', $on))
            ->where(fn ($q) => $q->whereNull('active_to')->orWhere('active_to', '>=', $on));
    }

    public function isActive(?\DateTimeInterface $on = null): bool
    {
        $on = $on ?? now();
        if ($this->active_from !== null && $this->active_from->isAfter($on)) {
            return false;
        }
        if ($this->active_to !== null && $this->active_to->isBefore($on)) {
            return false;
        }

        return true;
    }
}
