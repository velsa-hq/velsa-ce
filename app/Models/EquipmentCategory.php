<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EquipmentCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentCategory extends Model
{
    /** @use HasFactory<EquipmentCategoryFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'department',
        'debit_account_code',
        'credit_account_code',
        'tax_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return HasMany<EquipmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EquipmentItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
