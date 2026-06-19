<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EquipmentItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class EquipmentItem extends Model
{
    /** @use HasFactory<EquipmentItemFactory> */
    use Auditable, HasFactory, Searchable;

    protected $fillable = [
        'equipment_category_id',
        'sku',
        'name',
        'description',
        'unit_label',
        'unit_price_cents',
        'advance_price_cents',
        'debit_account_code',
        'credit_account_code',
        'tax_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'advance_price_cents' => 'integer',
            'tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'sku';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EquipmentCategory::class, 'equipment_category_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category_name' => $this->category?->name,
            'is_active' => (bool) $this->is_active,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Item override, else category default. */
    public function effectiveCreditAccountCode(): ?string
    {
        return $this->credit_account_code
            ?? $this->category?->credit_account_code;
    }

    public function effectiveDebitAccountCode(): ?string
    {
        return $this->debit_account_code
            ?? $this->category?->debit_account_code;
    }

    /** Item override, else category default. Decimal, e.g. 0.07 for 7%. */
    public function effectiveTaxRate(): float
    {
        if ($this->tax_rate !== null) {
            return (float) $this->tax_rate;
        }

        return (float) ($this->category?->tax_rate ?? 0);
    }
}
