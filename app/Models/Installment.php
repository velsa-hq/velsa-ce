<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled payment in a PaymentSchedule. IssueDueInstallments issues
 * an invoice when due_date <= today and invoice_id is null; an observer
 * backfills paid_at when that invoice is paid.
 */
class Installment extends Model
{
    protected $fillable = [
        'payment_schedule_id',
        'sequence',
        'due_date',
        'amount_cents',
        'label',
        'invoice_id',
        'invoiced_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_cents' => 'integer',
            'sequence' => 'integer',
            'invoiced_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class, 'payment_schedule_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function isInvoiced(): bool
    {
        return $this->invoice_id !== null;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
