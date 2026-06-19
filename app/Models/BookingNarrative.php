<?php

namespace App\Models;

use App\Enums\BookingNarrativeKind;
use App\Models\Concerns\Auditable;
use Database\Factories\BookingNarrativeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a booking's running narrative (call summary, decision note,
 * meeting recap). Append-only by convention.
 */
class BookingNarrative extends Model
{
    /** @use HasFactory<BookingNarrativeFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'booking_id',
        'author_user_id',
        'kind',
        'body',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BookingNarrativeKind::class,
            'happened_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
