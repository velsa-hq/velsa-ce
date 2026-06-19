<?php

namespace App\Models;

use Database\Factories\DiagramFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Diagram extends Model
{
    /** @use HasFactory<DiagramFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'space_id',
        'current_version_id',
        'name',
        'scale_px_per_foot',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'scale_px_per_foot' => 'float',
            'locked_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DiagramVersion::class)->orderBy('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DiagramVersion::class, 'current_version_id');
    }

    /**
     * Record a new version of the diagram and make it current.
     *
     * @param  array<int, array<string, mixed>>  $objects
     */
    public function saveVersion(array $objects, ?int $userId = null, ?string $note = null): DiagramVersion
    {
        $nextVersion = (int) $this->versions()->max('version') + 1;

        $created = $this->versions()->create([
            'version' => $nextVersion,
            'created_by_user_id' => $userId,
            'objects_json' => $objects,
            'note' => $note,
        ]);

        $this->forceFill(['current_version_id' => $created->id])->save();

        return $created;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
