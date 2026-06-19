<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ExhibitorPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExhibitorPayment extends Model
{
    /** @use HasFactory<ExhibitorPaymentFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'exhibitor_order_id',
        'provider',
        'provider_transaction_id',
        'status',
        'amount_cents',
        'refunded_amount_cents',
        'refunded_at',
        'last4',
        'card_brand',
        'idempotency_key',
        'processed_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'refunded_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ExhibitorOrder::class, 'exhibitor_order_id');
    }

    /** Captured minus refunded; refunds may be partial. */
    public function effectiveAmountCents(): int
    {
        return max(0, $this->amount_cents - $this->refunded_amount_cents);
    }

    public function refundableAmountCents(): int
    {
        if ($this->status !== PaymentStatus::Captured) {
            return 0;
        }

        return $this->effectiveAmountCents();
    }

    public function isFullyRefunded(): bool
    {
        return $this->refunded_amount_cents >= $this->amount_cents;
    }
}
