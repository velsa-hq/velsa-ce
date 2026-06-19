<?php

namespace App\Models;

use Database\Factories\AuditRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-defined audit rule: an active rule flags any audit event whose
 * event_type starts with the rule's event_type prefix.
 *
 * @property int $id
 * @property string $name
 * @property string $event_type
 * @property string|null $description
 * @property bool $is_active
 */
class AuditRule extends Model
{
    /** @use HasFactory<AuditRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'event_type',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
