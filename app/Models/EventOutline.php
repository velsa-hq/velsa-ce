<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EventOutlineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventOutline extends Model
{
    /** @use HasFactory<EventOutlineFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'booking_id',
        'published_version',
        'published_at',
        'published_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'published_version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OutlineItem::class)->orderBy('scheduled_at');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    // bump version so each publish is distinct in the audit log; the
    // shift-reminder listener keys off published_at changing
    public function publish(?int $userId = null): void
    {
        $this->update([
            'published_version' => $this->published_version + 1,
            'published_at' => now(),
            'published_by_user_id' => $userId,
        ]);
    }
}
