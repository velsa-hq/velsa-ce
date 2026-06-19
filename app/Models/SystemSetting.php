<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One key/value row in the system-settings table. Read through the
 * SystemSettings service (caches, decrypts secrets, applies env/default
 * fallback); direct model access is for the admin write surface.
 */
class SystemSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'key',
        'value',
        'is_secret',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
        ];
    }

    /**
     * Strip secret rows' values from the audit diff so credentials
     * (DocuSign keys, BluePay merchant ids) never reach the audit log.
     *
     * @return list<string>
     */
    public function auditExcludedKeys(): array
    {
        return $this->is_secret ? ['value'] : [];
    }
}
