<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDisplayImage;
use App\Support\Markdown;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property array<string, mixed>|null $settings_json
 */
class Venue extends Model implements HasMedia
{
    /** @use HasFactory<VenueFactory> */
    use Auditable, HasDisplayImage, HasFactory, Searchable, SoftDeletes;

    const DELETED_AT = 'retired_at';

    protected $fillable = [
        'name',
        'slug',
        'address_json',
        'timezone',
        'phone',
        'website',
        'settings_json',
        'exhibitor_handbook_md',
        'exhibitor_handbook_published_at',
        'active_at',
        'retired_at',
    ];

    protected function casts(): array
    {
        return [
            'address_json' => 'array',
            'settings_json' => 'array',
            'exhibitor_handbook_published_at' => 'datetime',
            'active_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }

    public function hasPublishedExhibitorHandbook(): bool
    {
        return $this->exhibitor_handbook_published_at !== null
            && trim((string) $this->exhibitor_handbook_md) !== '';
    }

    public function exhibitorHandbookHtml(): string
    {
        return Markdown::toHtml($this->exhibitor_handbook_md);
    }

    /**
     * Whether conflict checks count setup/teardown buffers as occupied time.
     * Off by default; opt in per venue.
     */
    public function enforcesSetupBuffers(): bool
    {
        return (bool) ($this->settings_json['enforce_setup_buffers'] ?? false);
    }

    protected static function booted(): void
    {
        static::creating(function (Venue $venue): void {
            if (empty($venue->slug) && ! empty($venue->name)) {
                $venue->slug = static::generateUniqueSlug($venue->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'venue';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city' => $this->address_json['city'] ?? null,
            'state' => $this->address_json['state'] ?? null,
            'summary' => $this->settings_json['summary'] ?? null,
        ];
    }

    public function blackouts(): MorphMany
    {
        return $this->morphMany(Blackout::class, 'blackoutable');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function workOrderTemplates(): HasMany
    {
        return $this->hasMany(WorkOrderTemplate::class);
    }

    /** @return HasMany<RateCard, $this> */
    public function rateCards(): HasMany
    {
        return $this->hasMany(RateCard::class);
    }

    /** @return HasMany<RatePackage, $this> */
    public function ratePackages(): HasMany
    {
        return $this->hasMany(RatePackage::class);
    }

    public function resourceInventories(): HasMany
    {
        return $this->hasMany(ResourceInventory::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('active_at')->where('active_at', '<=', now());
    }

    public function scopeComingSoon(Builder $query): Builder
    {
        return $query->whereNull('active_at');
    }

    public function isActive(): bool
    {
        return $this->active_at !== null
            && $this->active_at->isPast()
            && $this->retired_at === null;
    }
}
