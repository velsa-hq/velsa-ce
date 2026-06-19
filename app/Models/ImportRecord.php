<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Maps a committed import to a row it inserted; a reversal walks these to delete only those.
 *
 * @property int $id
 * @property int $import_job_id
 * @property string $importable_type
 * @property int $importable_id
 * @property int $row_number
 */
class ImportRecord extends Model
{
    protected $fillable = [
        'import_job_id',
        'importable_type',
        'importable_id',
        'row_number',
    ];

    /** @return BelongsTo<ImportJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    /** @return MorphTo<Model, $this> */
    public function importable(): MorphTo
    {
        return $this->morphTo();
    }
}
