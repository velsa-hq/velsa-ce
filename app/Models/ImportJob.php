<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\ImportJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded file moving through the upload -> map -> preview -> commit pipeline.
 *
 * @property int $id
 * @property string $kind
 * @property ImportStatus $status
 * @property string $original_filename
 * @property string $disk
 * @property string $file_path
 * @property bool $has_header
 * @property string $delimiter
 * @property array<string, string>|null $column_map
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $created_rows
 * @property int $error_rows
 * @property bool $read_only_covered
 * @property array<string, mixed>|null $summary_json
 * @property CarbonImmutable|null $previewed_at
 * @property CarbonImmutable|null $committed_at
 * @property CarbonImmutable|null $reversed_at
 * @property int|null $created_by_user_id
 */
class ImportJob extends Model
{
    /** @use HasFactory<ImportJobFactory> */
    use Auditable, HasFactory;

    /** Days after commit during which an import can still be reversed. */
    public const REVERSAL_WINDOW_DAYS = 7;

    protected $fillable = [
        'kind',
        'status',
        'original_filename',
        'disk',
        'file_path',
        'has_header',
        'delimiter',
        'column_map',
        'total_rows',
        'valid_rows',
        'created_rows',
        'error_rows',
        'read_only_covered',
        'summary_json',
        'previewed_at',
        'committed_at',
        'reversed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'has_header' => 'boolean',
            'column_map' => 'array',
            'read_only_covered' => 'boolean',
            'summary_json' => 'array',
            'previewed_at' => 'datetime',
            'committed_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /** @return HasMany<ImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    /** @return HasMany<ImportRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(ImportRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Completed, within the reversal window, and actually inserted rows. */
    public function isReversible(): bool
    {
        return $this->status === ImportStatus::Completed
            && $this->committed_at !== null
            && $this->committed_at->addDays(self::REVERSAL_WINDOW_DAYS)->isFuture()
            && $this->created_rows > 0;
    }
}
