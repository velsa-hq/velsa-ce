<?php

namespace App\Models;

use App\Enums\ContractStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use RuntimeException;

/**
 * Once signed, a Contract is immutable; further changes must be a new
 * Addendum (child row via parent_contract_id). Enforced in updating() below.
 *
 * @property ContractStatus|null $status
 */
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use Auditable, HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'template_id',
        'parent_contract_id',
        'created_by_user_id',
        'reference',
        'kind',
        'status',
        'total_cents',
        'rendered_html',
        'pdf_s3_key',
        'provider',
        'provider_envelope_id',
        'sent_at',
        'viewed_at',
        'signed_at',
        'declined_at',
        'expired_at',
        'voided_at',
        'decline_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'total_cents' => 'integer',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            if (empty($contract->reference)) {
                $contract->reference = static::generateReference($contract->kind ?? 'contract');
            }
        });

        static::updating(function (Contract $contract): void {
            $original = $contract->getOriginal('status');
            $originalEnum = $original instanceof ContractStatus
                ? $original
                : ($original ? ContractStatus::from((string) $original) : null);

            // signed contracts are immutable; only the explicit void() workflow may transition them
            if ($originalEnum?->isImmutable() && $contract->isDirty(['total_cents', 'rendered_html', 'template_id', 'sent_at', 'viewed_at', 'signed_at', 'provider_envelope_id'])) {
                throw new RuntimeException('Contract is signed and immutable - create an addendum instead.');
            }
        });
    }

    public static function generateReference(string $kind = 'contract'): string
    {
        $prefix = match ($kind) {
            'addendum' => 'AD',
            'invoice' => 'INV',
            default => 'CT',
        };
        $year = date('Y');
        do {
            $suffix = strtoupper(Str::random(5));
            $candidate = "{$prefix}-{$year}-{$suffix}";
        } while (static::query()->where('reference', $candidate)->exists());

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'kind' => $this->kind,
            'status' => $this->status?->value,
            'booking_reference' => $this->booking?->reference,
            'booking_name' => $this->booking?->name,
            'client_name' => $this->booking?->client?->name,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(ContractSigner::class)->orderBy('signing_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Returns false if not in Draft (already sent). */
    public function markSent(string $providerEnvelopeId): bool
    {
        if ($this->status !== ContractStatus::Draft) {
            return false;
        }

        $this->update([
            'status' => ContractStatus::Sent->value,
            'provider_envelope_id' => $providerEnvelopeId,
            'sent_at' => now(),
        ]);

        return true;
    }

    public function markViewedBy(ContractSigner $signer): void
    {
        // a finalized contract can't be re-opened by a late/stray event
        if ($this->status?->isTerminal()) {
            return;
        }

        $signer->update(['viewed_at' => $signer->viewed_at ?? now()]);

        if ($this->viewed_at === null) {
            $this->update([
                'status' => ContractStatus::Viewed->value,
                'viewed_at' => now(),
            ]);
        }
    }

    public function markSignedBy(ContractSigner $signer): void
    {
        if ($this->status?->isTerminal()) {
            return;
        }

        $signer->update([
            'signed_at' => $signer->signed_at ?? now(),
        ]);

        $allSigned = ! $this->signers()->whereNull('signed_at')->exists();

        $this->update([
            'status' => $allSigned ? ContractStatus::Signed->value : ContractStatus::PartiallySigned->value,
            'signed_at' => $allSigned ? now() : null,
        ]);
    }

    public function markDeclined(ContractSigner $signer, ?string $reason = null): void
    {
        if ($this->status?->isTerminal()) {
            return;
        }

        $signer->update([
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);

        $this->update([
            'status' => ContractStatus::Declined->value,
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);
    }

    /**
     * Force-cancel an in-flight contract. Records local terminal state only;
     * the provider envelope-void call is the integration layer's job. No-op unless voidable.
     */
    public function void(?string $reason = null): bool
    {
        if (! ($this->status?->isVoidable() ?? false)) {
            return false;
        }

        $this->update([
            'status' => ContractStatus::Voided->value,
            'voided_at' => now(),
            'decline_reason' => $reason ?? $this->decline_reason,
        ]);

        return true;
    }

    /** No-op unless currently Sent or Viewed. */
    public function markExpired(): bool
    {
        if ($this->status !== ContractStatus::Sent && $this->status !== ContractStatus::Viewed) {
            return false;
        }

        $this->update([
            'status' => ContractStatus::Expired->value,
            'expired_at' => now(),
        ]);

        return true;
    }
}
