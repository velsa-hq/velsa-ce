<?php

namespace App\Models;

use Database\Factories\ExhibitorEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $settings_json
 * @property Carbon|null $advance_rate_deadline
 * @property int $late_order_surcharge_pct
 */
class ExhibitorEvent extends Model
{
    /** @use HasFactory<ExhibitorEventFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'name',
        'portal_slug',
        'default_booth_size',
        'registration_opens_at',
        'registration_closes_at',
        'advance_rate_deadline',
        'late_order_surcharge_pct',
        'settings_json',
    ];

    protected function casts(): array
    {
        return [
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'advance_rate_deadline' => 'datetime',
            'late_order_surcharge_pct' => 'integer',
            'settings_json' => 'array',
        ];
    }

    /** Surcharge configured and advance-rate deadline passed. */
    public function lateRateActive(): bool
    {
        return $this->advance_rate_deadline !== null
            && $this->late_order_surcharge_pct > 0
            && now()->greaterThan($this->advance_rate_deadline);
    }

    /** Base advance price, plus late surcharge once the deadline passes. */
    public function pricedNowCents(int $baseCents): int
    {
        if (! $this->lateRateActive()) {
            return $baseCents;
        }

        return (int) round($baseCents * (1 + $this->late_order_surcharge_pct / 100));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function exhibitors(): HasMany
    {
        return $this->hasMany(Exhibitor::class);
    }

    /** Auto-generate work orders for confirmed orders; defaults on. */
    public function generatesWorkOrders(): bool
    {
        return (bool) ($this->settings_json['generate_work_orders'] ?? true);
    }

    /** When work orders fire: 'confirm' (default) or 'paid'. */
    public function workOrderTrigger(): string
    {
        return $this->settings_json['work_order_trigger'] ?? 'confirm';
    }

    public function isRegistrationOpen(): bool
    {
        $now = now();

        return ($this->registration_opens_at === null || $this->registration_opens_at->lte($now))
            && ($this->registration_closes_at === null || $this->registration_closes_at->gte($now));
    }
}
