<?php

namespace App\Models;

use App\Reports\ReportDatasource;
use Database\Factories\ReportDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReportDefinition extends Model
{
    /** @use HasFactory<ReportDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'datasource',
        'filters_json',
        'dimensions_json',
        'metrics_json',
        'sort_json',
        'row_limit',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'datasource' => ReportDatasource::class,
            'filters_json' => 'array',
            'dimensions_json' => 'array',
            'metrics_json' => 'array',
            'sort_json' => 'array',
            'row_limit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $def): void {
            if (empty($def->slug) && ! empty($def->name)) {
                $def->slug = static::generateUniqueSlug($def->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'report';
        $slug = $base;
        $i = 2;
        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class)->orderByDesc('generated_at');
    }

    public function lastRun(): ?ReportRun
    {
        return $this->runs()->first();
    }
}
