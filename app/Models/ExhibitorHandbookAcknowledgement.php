<?php

namespace App\Models;

use App\Models\Concerns\IsVenueScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Records that an exhibitor acknowledged a venue's exhibitor handbook.
 *
 * @property int $exhibitor_id
 * @property int $venue_id
 * @property Carbon $acknowledged_at
 */
class ExhibitorHandbookAcknowledgement extends Model
{
    use IsVenueScoped;

    protected $fillable = [
        'exhibitor_id',
        'venue_id',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }
}
