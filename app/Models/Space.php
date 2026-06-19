<?php

namespace App\Models;

use App\Enums\BookableUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDisplayImage;
use App\Models\Concerns\IsVenueScoped;
use Database\Factories\SpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property-read Venue|null $venue
 * @property int|null $parent_space_id
 */
class Space extends Model implements HasMedia
{
    /** @use HasFactory<SpaceFactory> */
    use Auditable, HasFactory, IsVenueScoped, Searchable, SoftDeletes;

    use HasDisplayImage {
        registerMediaCollections as registerDisplayImageCollections;
    }

    const DELETED_AT = 'retired_at';

    protected $fillable = [
        'venue_id',
        'parent_space_id',
        'name',
        'kind',
        'capacity',
        'sqft',
        'dimensions_json',
        'bookable_unit',
        'attributes_json',
        'constraints_json',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'bookable_unit' => BookableUnit::class,
            'dimensions_json' => 'array',
            'attributes_json' => 'array',
            'constraints_json' => 'array',
            'retired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Space $space): void {
            $space->assertParentIsAcyclic();
            $space->assertParentSharesVenue();
        });
    }

    // adds a single-file "floorplan" collection alongside the display-image "photo" one
    public function registerMediaCollections(): void
    {
        $this->registerDisplayImageCollections();

        $this->addMediaCollection('floorplan')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function floorPlanUrl(): ?string
    {
        $media = $this->getFirstMedia('floorplan');

        if ($media === null) {
            return null;
        }

        if ($media->disk !== '' && config("filesystems.disks.{$media->disk}.driver") === 's3') {
            return $media->getTemporaryUrl(now()->addMinutes(30));
        }

        return $media->getUrl();
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    // soft reference: spaces.kind holds the SpaceKind key slug, not an FK, so a
    // kind can be renamed/retired without rewriting space rows; null if no match
    public function kindRef(): BelongsTo
    {
        return $this->belongsTo(SpaceKind::class, 'kind', 'key');
    }

    // falls back to a title-cased slug when no SpaceKind row matches
    public function kindLabel(): string
    {
        return $this->kindRef?->label
            ?? ucwords(str_replace('_', ' ', (string) $this->kind));
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'venue_name' => $this->venue?->name,
            'venue_slug' => $this->venue?->slug,
            'capacity' => (int) $this->capacity,
        ];
    }

    /**
     * Validate space ids as a bookable combination against the adjacency tree:
     *  1. a selected non-leaf space must include all of its direct children (or none)
     *  2. selection roots (parent not selected) must share one parent, so the
     *     selection can't span disconnected branches of the layout
     *
     * @param  array<int>  $selectedIds
     * @return list<string> error messages; empty = valid
     */
    public static function validateSubset(array $selectedIds): array
    {
        if (count($selectedIds) <= 1) {
            return [];
        }

        $selectedIds = array_map('intval', $selectedIds);
        $selectedSet = array_flip($selectedIds);

        $spaces = static::query()
            ->whereIn('id', $selectedIds)
            ->get(['id', 'name', 'parent_space_id'])
            ->keyBy('id');

        $childrenByParent = static::query()
            ->whereIn('parent_space_id', $selectedIds)
            ->get(['id', 'name', 'parent_space_id'])
            ->groupBy('parent_space_id');

        $errors = [];

        // rule 1: parent + any child -> must include all children
        foreach ($selectedIds as $parentId) {
            $children = $childrenByParent->get($parentId);
            if ($children === null || $children->isEmpty()) {
                continue;
            }

            $missing = $children->reject(fn ($c) => isset($selectedSet[$c->id]));
            $anySelected = $children->contains(fn ($c) => isset($selectedSet[$c->id]));

            if ($anySelected && $missing->isNotEmpty()) {
                $parentName = $spaces->get($parentId)?->name ?? "#{$parentId}";
                $missingNames = $missing->pluck('name')->implode(', ');
                $errors[] = "When booking \"{$parentName}\" together with any of its sub-spaces, every sub-space must be included. Missing: {$missingNames}.";
            }
        }

        // rule 2: selection roots must share a parent (siblings)
        $roots = $spaces->reject(
            fn ($s) => $s->parent_space_id !== null && isset($selectedSet[$s->parent_space_id]),
        );

        if ($roots->count() > 1) {
            $parentIds = $roots->pluck('parent_space_id')->unique();
            if ($parentIds->count() > 1) {
                $names = $roots->pluck('name')->map(fn ($n) => "\"{$n}\"")->implode(', ');
                $errors[] = "Spaces {$names} aren't adjacent - they live on different branches of the venue layout, so they can't be booked as a single combination.";
            }
        }

        return $errors;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Space::class, 'parent_space_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Space::class, 'parent_space_id');
    }

    public function blackouts(): MorphMany
    {
        return $this->morphMany(Blackout::class, 'blackoutable');
    }

    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
    }

    // blocks self-parenting and any cycle the new parent edge would form
    protected function assertParentIsAcyclic(): void
    {
        if ($this->parent_space_id === null) {
            return;
        }

        if ($this->exists && (int) $this->parent_space_id === (int) $this->getKey()) {
            throw new RuntimeException('A space cannot be its own parent.');
        }

        $visited = [];
        $cursorId = (int) $this->parent_space_id;

        while ($cursorId !== 0) {
            if ($this->exists && $cursorId === (int) $this->getKey()) {
                throw new RuntimeException('Setting this parent would create a cycle in the space hierarchy.');
            }

            if (isset($visited[$cursorId])) {
                throw new RuntimeException('Existing space hierarchy contains a cycle; refusing to save.');
            }
            $visited[$cursorId] = true;

            $next = static::query()
                ->withTrashed()
                ->whereKey($cursorId)
                ->value('parent_space_id');

            $cursorId = $next === null ? 0 : (int) $next;
        }
    }

    protected function assertParentSharesVenue(): void
    {
        if ($this->parent_space_id === null) {
            return;
        }

        $parentVenueId = static::query()
            ->withTrashed()
            ->whereKey($this->parent_space_id)
            ->value('venue_id');

        if ($parentVenueId !== null && (int) $parentVenueId !== (int) $this->venue_id) {
            throw new RuntimeException('Parent space must belong to the same venue.');
        }
    }
}
