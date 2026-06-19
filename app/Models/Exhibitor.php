<?php

namespace App\Models;

use Database\Factories\ExhibitorFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;

/**
 * External-user model. Authenticates against the `exhibitor` guard via
 * magic link (MagicLinkService); no password column.
 */
class Exhibitor extends Model implements Authenticatable
{
    /** @use HasFactory<ExhibitorFactory> */
    use AuthenticatableTrait, HasFactory, Searchable;

    protected $fillable = [
        'exhibitor_event_id',
        'company_name',
        'contact_name',
        'email',
        'phone',
        'booth_assignment',
        'booth_size',
        'address_json',
        'magic_token',
        'magic_token_expires_at',
    ];

    protected $hidden = [
        'magic_token',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'address_json' => 'array',
            'magic_token_expires_at' => 'datetime',
        ];
    }

    // lowercase on write: Postgres `=` is case-sensitive, so mixed-case
    // would silently miss on lookup. Mirrors User.
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    protected static function booted(): void
    {
        // delete through Eloquent so each order's deleting hook reverses
        // inventory deltas; then sweep work orders attached directly
        static::deleting(function (Exhibitor $exhibitor): void {
            foreach ($exhibitor->orders as $order) {
                $order->delete();
            }
            foreach ($exhibitor->workOrders as $workOrder) {
                $workOrder->reverseInventoryDeltas();
                $workOrder->delete();
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ExhibitorEvent::class, 'exhibitor_event_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'booth_assignment' => $this->booth_assignment,
            'event_name' => $this->event?->name,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(ExhibitorOrder::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** @return MorphMany<InsuranceCertificate, $this> */
    public function insuranceCertificates(): MorphMany
    {
        return $this->morphMany(InsuranceCertificate::class, 'holder');
    }

    /** @return HasMany<ExhibitorPermit, $this> */
    public function permits(): HasMany
    {
        return $this->hasMany(ExhibitorPermit::class);
    }

    // contract stub only; real verification is MagicLinkService::verify()
    public function getAuthPassword(): string
    {
        return (string) $this->magic_token;
    }

    public function getAuthPasswordName(): string
    {
        return 'magic_token';
    }

    // empty name disables remember-token handling; sessions are short-lived
    public function getRememberTokenName(): string
    {
        return '';
    }

    /** Most-recent unfinalized order, or null. */
    public function currentDraftOrder(): ?ExhibitorOrder
    {
        return $this->orders()
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderByDesc('id')
            ->first();
    }
}
