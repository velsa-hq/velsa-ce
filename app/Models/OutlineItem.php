<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OutlineItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface|null $scheduled_at
 */
class OutlineItem extends Model
{
    /** @use HasFactory<OutlineItemFactory> */
    use HasFactory;

    protected $fillable = [
        'event_outline_id',
        'space_id',
        'responsible_user_id',
        'scheduled_at',
        'duration_minutes',
        'department',
        'title',
        'description',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function outline(): BelongsTo
    {
        return $this->belongsTo(EventOutline::class, 'event_outline_id');
    }

    /** Soft reference by Department key slug (not an FK); null if the slug no longer matches. */
    public function departmentRef(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department', 'key');
    }

    /** Department label, falling back to a title-cased slug. */
    public function departmentLabel(): string
    {
        return $this->departmentRef?->label
            ?? Str::headline((string) $this->department);
    }

    public function departmentColor(): string
    {
        return $this->departmentRef?->color ?? 'slate';
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OutlineItemTask::class)->orderBy('position')->orderBy('id');
    }

    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->where('scheduled_at', '>=', $from)->where('scheduled_at', '<', $to);
    }

    public function scopeForDepartment(Builder $query, string $departmentKey): Builder
    {
        return $query->where('department', $departmentKey);
    }

    public function endsAt(): CarbonInterface
    {
        // CarbonImmutable at runtime via global Date::use(); CarbonInterface covers both
        return $this->scheduled_at->add('minutes', $this->duration_minutes);
    }
}
