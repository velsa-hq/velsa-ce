<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Models\Concerns\Auditable;
use Database\Factories\ExportTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Configurable journal export shape: CSV format options plus a column map.
 * FK is restrictOnDelete so a template referenced by an export batch can't be removed.
 */
class ExportTemplate extends Model
{
    /** @use HasFactory<ExportTemplateFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'format',
        'delimiter',
        'quote_char',
        'line_ending',
        'encoding',
        'include_header',
        'include_footer',
        'is_default',
        'file_extension',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'include_header' => 'boolean',
            'include_footer' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            if (empty($template->slug) && ! empty($template->name)) {
                $template->slug = static::generateUniqueSlug($template->name);
            }
        });

        static::saving(function (self $template): void {
            // at most one default; promoting a new one demotes the rest
            if ($template->is_default) {
                static::query()
                    ->where('is_default', true)
                    ->when($template->exists, fn ($q) => $q->where('id', '!=', $template->getKey()))
                    ->update(['is_default' => false]);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ExportTemplateColumn::class)->orderBy('sort_order');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(LedgerExportBatch::class, 'export_template_id');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /** Default-flagged template, else newest by id (fresh-install fallback). */
    public static function resolveDefault(): ?self
    {
        return static::query()->default()->first()
            ?? static::query()->orderByDesc('id')->first();
    }
}
