<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single row's failure within an import job.
 *
 * @property int $id
 * @property int $import_job_id
 * @property int $row_number
 * @property string|null $field
 * @property string $message
 * @property array<int|string, mixed>|null $raw_row_json
 */
class ImportError extends Model
{
    protected $fillable = [
        'import_job_id',
        'row_number',
        'field',
        'message',
        'raw_row_json',
    ];

    protected function casts(): array
    {
        return [
            'raw_row_json' => 'array',
        ];
    }

    /** @return BelongsTo<ImportJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
