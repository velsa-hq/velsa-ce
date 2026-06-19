<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One PaymentSchedule of N Installments per Booking. Mutually exclusive
 * with Booking's two-phase deposit/balance flow - use one, not both
 * (see PaymentScheduleService::createForBooking).
 *
 * `total_cents` is a cached installment sum; PaymentScheduleService
 * keeps it in sync on every replace.
 */
class PaymentSchedule extends Model
{
    protected $fillable = [
        'booking_id',
        'total_cents',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'activated_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('sequence');
    }
}
