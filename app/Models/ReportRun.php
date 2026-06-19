<?php

namespace App\Models;

use Database\Factories\ReportRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportRun extends Model
{
    /** @use HasFactory<ReportRunFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'report_definition_id',
        'params_json',
        'row_count',
        'summary_json',
        'duration_ms',
        'generated_by_user_id',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'params_json' => 'array',
            'summary_json' => 'array',
            'row_count' => 'integer',
            'duration_ms' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }
}
