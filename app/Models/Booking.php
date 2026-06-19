<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\HoldRank;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocuments;
use App\Models\Concerns\IsVenueScoped;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;

/**
 * Top-level booking record, owning 1+ BookingSpace rows. Status transitions
 * are governed by BookingStatus. Reference auto-generates as BK-YYYY-NNNNN.
 *
 * @property int $id
 * @property BookingStatus $status
 * @property ?HoldRank $hold_rank
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property string|null $cancel_reason
 */
class Booking extends Model implements HasMedia
{
    /** @use HasFactory<BookingFactory> */
    use Auditable, HasDocuments, HasFactory, IsVenueScoped, Searchable;

    protected $fillable = [
        'venue_id',
        'client_id',
        'lead_id',
        'owner_user_id',
        'reference',
        'name',
        'kind',
        'status',
        'hold_rank',
        'hold_expires_at',
        'start_at',
        'end_at',
        'total_cents',
        'deposit_percent',
        'attendance_estimate',
        'attendance_actual',
        'notes',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'hold_rank' => HoldRank::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_cents' => 'integer',
            'deposit_percent' => 'decimal:2',
            'attendance_estimate' => 'integer',
            'attendance_actual' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (empty($booking->reference)) {
                $booking->reference = static::generateReference();
            }
        });

        static::saving(function (Booking $booking): void {
            if ($booking->isDirty('status')) {
                $newStatus = $booking->status instanceof BookingStatus
                    ? $booking->status
                    : BookingStatus::from((string) $booking->status);

                if ($newStatus === BookingStatus::Cancelled && $booking->cancelled_at === null) {
                    $booking->cancelled_at = now();
                }

                // confirming a hold bypasses BookingSpace's save-time overlap
                // check, so re-check here for a clean error over a raw DB violation
                if ($booking->exists && $newStatus->blocksOverlap()) {
                    $booking->assertSpacesFreeForBlocking();
                }
            }
        });

        // keep child spaces' denormalized blocks_overlap in sync; it drives
        // the DB exclusion constraint and in-app availability checks
        static::saved(function (Booking $booking): void {
            if ($booking->wasChanged('status')) {
                $booking->spaces()->update(['blocks_overlap' => $booking->status->blocksOverlap()]);
            }
        });
    }

    /**
     * Reject a blocking transition if any space overlaps another blocking
     * booking. Mirrors the DB exclusion constraint with a readable message;
     * buffer-window conflicts stay enforced at BookingSpace save.
     */
    public function assertSpacesFreeForBlocking(): void
    {
        foreach ($this->spaces as $space) {
            $conflict = BookingSpace::query()
                ->where('space_id', $space->space_id)
                ->where('booking_id', '!=', $this->getKey())
                ->where('blocks_overlap', true)
                ->where('start_at', '<', $space->end_at)
                ->where('end_at', '>', $space->start_at)
                ->first();

            if ($conflict !== null) {
                throw new \RuntimeException(sprintf(
                    'Cannot confirm - space is already booked in this window by booking #%d.',
                    $conflict->booking_id,
                ));
            }
        }
    }

    /**
     * Search index document. Denormalizes client + venue names so a query
     * matches them without joining at search time.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'reference' => $this->reference,
            'name' => $this->name,
            'kind' => $this->kind,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'client_name' => $this->client?->name,
            'venue_name' => $this->venue?->name,
            'starts_at' => $this->start_at?->timestamp,
        ];
    }

    public static function generateReference(): string
    {
        $year = date('Y');
        do {
            $suffix = strtoupper(Str::random(5));
            $candidate = "BK-{$year}-{$suffix}";
        } while (static::query()->where('reference', $candidate)->exists());

        return $candidate;
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<BookingSpace, $this> */
    public function spaces(): HasMany
    {
        return $this->hasMany(BookingSpace::class);
    }

    public function narratives(): HasMany
    {
        return $this->hasMany(BookingNarrative::class);
    }

    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function paymentSchedule(): HasOne
    {
        return $this->hasOne(PaymentSchedule::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class)->orderBy('start_at');
    }

    /** Sum of total_cents across non-void invoices, for remaining-balance. */
    public function invoicedCents(): int
    {
        return (int) $this->invoices()
            ->whereNotIn('status', [
                InvoiceStatus::Void->value,
                InvoiceStatus::WrittenOff->value,
            ])
            ->sum('total_cents');
    }

    public function remainingToInvoiceCents(): int
    {
        return max(0, $this->total_cents - $this->invoicedCents());
    }

    public function scopeWithStatus(Builder $query, BookingStatus ...$statuses): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, $statuses));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now())->orderBy('start_at');
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('start_at', '<', $to)->where('end_at', '>', $from);
    }
}
