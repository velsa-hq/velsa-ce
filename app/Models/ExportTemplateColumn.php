<?php

namespace App\Models;

use Database\Factories\ExportTemplateColumnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportTemplateColumn extends Model
{
    /** @use HasFactory<ExportTemplateColumnFactory> */
    use HasFactory;

    protected $fillable = [
        'export_template_id',
        'sort_order',
        'label',
        'source',
        'format_mask',
        'default_value',
        'width',
        'align',
        'pad_char',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'width' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExportTemplate::class, 'export_template_id');
    }
}
