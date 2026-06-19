<?php

namespace App\Models;

use App\Enums\ExhibitorPermitStatus;
use App\Enums\ExhibitorPermitType;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\ExhibitorPermitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Exhibitor request for a regulated booth activity, reviewed by venue staff.
 * Optional supporting doc lives in the single-file "document" media collection.
 *
 * @property int $id
 * @property int $exhibitor_id
 * @property ExhibitorPermitType $permit_type
 * @property string $details
 * @property ExhibitorPermitStatus $status
 * @property string|null $review_notes
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 * @property bool $submitted_via_portal
 */
class ExhibitorPermit extends Model implements HasMedia
{
    /** @use HasFactory<ExhibitorPermitFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'exhibitor_id',
        'permit_type',
        'details',
        'status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'submitted_via_portal',
    ];

    protected function casts(): array
    {
        return [
            'permit_type' => ExhibitorPermitType::class,
            'status' => ExhibitorPermitStatus::class,
            'reviewed_at' => 'datetime',
            'submitted_via_portal' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('document')
            ->useDisk(config('media-library.private_disk'))
            ->singleFile()
            ->acceptsMimeTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp']);
    }

    /** @return BelongsTo<Exhibitor, $this> */
    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(Exhibitor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ExhibitorPermitStatus::Pending;
    }

    /** Routes through the authenticated media gateway, not a public /storage URL, so downloads stay authorized per requester. */
    public function documentUrl(): ?string
    {
        $media = $this->getFirstMedia('document');

        return $media === null ? null : route('media.show', $media);
    }
}
