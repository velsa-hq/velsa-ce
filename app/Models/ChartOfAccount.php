<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Models\Concerns\Auditable;
use Database\Factories\ChartOfAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $code
 * @property string $name
 * @property AccountType|null $account_type
 * @property string $normal_balance
 */
class ChartOfAccount extends Model
{
    /** @use HasFactory<ChartOfAccountFactory> */
    use Auditable, HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'description',
        'account_type',
        'account_subtype',
        'normal_balance',
        'parent_account_id',
        'is_postable',
        'active_from',
        'active_to',
    ];

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'is_postable' => 'boolean',
            'active_from' => 'date',
            'active_to' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // derive normal_balance from account_type when not supplied
        static::saving(function (self $account): void {
            if (empty($account->normal_balance) && $account->account_type !== null) {
                $account->normal_balance = $account->account_type->normalBalance();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true);
    }

    public function scopeOfType(Builder $query, AccountType $type): Builder
    {
        return $query->where('account_type', $type->value);
    }

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

    public function isPostable(): bool
    {
        return (bool) $this->is_postable;
    }
}
