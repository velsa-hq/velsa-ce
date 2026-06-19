<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id',
        'position',
        'description',
        'detail',
        'quantity',
        'unit_price_cents',
        'line_total_cents',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quantity' => 'integer',
            'unit_price_cents' => 'integer',
            'line_total_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // keep line_total_cents derived from quantity x unit_price
        static::saving(function (InvoiceLine $line) {
            $line->line_total_cents = $line->quantity * $line->unit_price_cents;
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
