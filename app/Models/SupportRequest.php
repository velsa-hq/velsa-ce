<?php

namespace App\Models;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * In-app support request from a signed-in user. Persisted so nothing is lost
 * when email delivery is down; optionally emailed to support on submission.
 *
 * @property int $id
 * @property int|null $user_id
 * @property SupportRequestCategory $category
 * @property string $subject
 * @property string $body
 * @property string|null $page_url
 * @property string|null $app_version
 * @property SupportRequestStatus $status
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolved_by
 * @property CarbonImmutable $created_at
 */
class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'subject',
        'body',
        'page_url',
        'app_version',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportRequestCategory::class,
            'status' => SupportRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
