<?php

namespace App\Models;

use Database\Factories\DiagramVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagramVersion extends Model
{
    /** @use HasFactory<DiagramVersionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'diagram_id',
        'version',
        'created_by_user_id',
        'objects_json',
        'thumbnail_s3_key',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'objects_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function diagram(): BelongsTo
    {
        return $this->belongsTo(Diagram::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
