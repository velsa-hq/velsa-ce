<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\IsTaxonomy;
use Database\Factories\SpaceKindFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User-definable taxonomy of space types (room, ballroom, arena, ...).
 * A space stores its kind as the `key` slug in `spaces.kind`; this table
 * owns the option list, labels, ordering, and active state.
 * `is_system` rows are seeded defaults, protected from deletion.
 */
class SpaceKind extends Model
{
    /** @use HasFactory<SpaceKindFactory> */
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

    // soft reference: spaces.kind holds the key slug, not an FK, so it
    // survives a kind being renamed or removed
    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class, 'kind', 'key');
    }
}
