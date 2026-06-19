<?php

namespace App\Models;

use App\Enums\InsuranceCertificateStatus;
use App\Enums\InsurancePolicyType;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\InsuranceCertificateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Certificate of Insurance (COI) against a polymorphic holder (Client or
 * Exhibitor). Document lives in the single-file "certificate" collection.
 *
 * @property int $id
 * @property string $holder_type
 * @property int $holder_id
 * @property InsurancePolicyType $policy_type
 * @property string|null $carrier
 * @property string|null $policy_number
 * @property int|null $coverage_amount_cents
 * @property CarbonImmutable|null $effective_date
 * @property CarbonImmutable $expires_on
 * @property InsuranceCertificateStatus $status
 * @property string|null $notes
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property bool $submitted_via_portal
 * @property int|null $created_by
 */
class InsuranceCertificate extends Model implements HasMedia
{
    /** @use HasFactory<InsuranceCertificateFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'holder_type',
        'holder_id',
        'policy_type',
        'carrier',
        'policy_number',
        'coverage_amount_cents',
        'effective_date',
        'expires_on',
        'status',
        'notes',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'submitted_via_portal',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'policy_type' => InsurancePolicyType::class,
            'status' => InsuranceCertificateStatus::class,
            'effective_date' => 'date',
            'expires_on' => 'date',
            'reviewed_at' => 'datetime',
            'submitted_via_portal' => 'boolean',
            'coverage_amount_cents' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('certificate')
            ->useDisk(config('media-library.private_disk'))
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return MorphTo<Model, $this> */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Approved certificates expiring on or before $date; drives both the
     * auto-expire sweep and the reminder digest.
     *
     * @param  Builder<InsuranceCertificate>  $query
     */
    public function scopeExpiringBy(Builder $query, CarbonImmutable $date): void
    {
        $query->where('status', InsuranceCertificateStatus::Approved)
            ->whereDate('expires_on', '<=', $date->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->status === InsuranceCertificateStatus::Expired
            || $this->expires_on->isPast();
    }

    /**
     * Document URL, or null if none attached. Routes through the authenticated
     * media gateway, never a public /storage URL, so access is authorized per requester.
     */
    public function documentUrl(): ?string
    {
        $media = $this->getFirstMedia('certificate');

        return $media === null ? null : route('media.show', $media);
    }
}
