<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A previously-used password hash, retained for reuse prevention
 * (APSC-DV-001780). Only `created_at` is tracked (no updated_at).
 */
class PasswordHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'password'];

    protected $hidden = ['password'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
