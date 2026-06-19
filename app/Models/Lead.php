<?php

namespace App\Models;

use App\Enums\LeadStage;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsVenueScoped;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use Auditable, HasFactory, IsVenueScoped;

    protected $fillable = [
        'client_id',
        'venue_id',
        'owner_user_id',
        'name',
        'stage',
        'estimated_value_cents',
        'probability',
        'expected_close_date',
        'source',
        'lost_reason',
        'notes',
        'closed_at',
        'archived_at',
        'converted_at',
        'converted_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'estimated_value_cents' => 'integer',
            'probability' => 'float',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead): void {
            if ($lead->isDirty('stage')) {
                $stage = $lead->stage instanceof LeadStage
                    ? $lead->stage
                    : LeadStage::from((string) $lead->stage);

                if ($stage->isTerminal() && $lead->closed_at === null) {
                    $lead->closed_at = now();
                }
                if (! $stage->isTerminal()) {
                    $lead->closed_at = null;
                }
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function convertedBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'converted_booking_id');
    }

    public function weightedValueCents(): int
    {
        return (int) round($this->estimated_value_cents * $this->probability);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [LeadStage::Won->value, LeadStage::Lost->value]);
    }

    public function scopeOnBoard(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    /**
     * Closed leads eligible to be archived: terminal, not archived, closed
     * before the cutoff, and not still future-dated (event hasn't happened).
     */
    public function scopeArchivable(Builder $query, int $afterDays): Builder
    {
        return $query
            ->whereIn('stage', [LeadStage::Won->value, LeadStage::Lost->value])
            ->whereNull('archived_at')
            ->whereNotNull('closed_at')
            ->where('closed_at', '<', now()->subDays($afterDays))
            ->where(function (Builder $q): void {
                $q->whereNull('expected_close_date')
                    ->orWhereDate('expected_close_date', '<', now()->toDateString());
            });
    }

    public function scopeForOwner(Builder $query, int $userId): Builder
    {
        return $query->where('owner_user_id', $userId);
    }

    public function scopeAtStage(Builder $query, LeadStage $stage): Builder
    {
        return $query->where('stage', $stage->value);
    }
}
