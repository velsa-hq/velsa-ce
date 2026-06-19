<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsTaxonomy;
use Database\Factories\EventKindFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User-definable taxonomy of event kinds (wedding, conference, expo, ...).
 * Bookings store their kind as the `key` slug in `bookings.kind` (soft reference).
 *
 * @see IsTaxonomy
 */
class EventKind extends Model
{
    /** @use HasFactory<EventKindFactory> */
    use Auditable, HasFactory, IsTaxonomy;

    protected $fillable = [
        'key',
        'label',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /** Soft reference via `bookings.kind` slug; survives rename/remove. */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'kind', 'key');
    }
}
