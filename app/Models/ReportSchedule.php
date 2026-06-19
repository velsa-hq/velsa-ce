<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ReportScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring emailed report delivery.
 *
 * @property int $id
 * @property string $report_slug
 * @property array<string, mixed>|null $params_json
 * @property string $format
 * @property string $frequency
 * @property int|null $day_of_week
 * @property int|null $day_of_month
 * @property int $hour
 * @property list<string> $recipients
 * @property bool $is_active
 * @property CarbonImmutable|null $last_run_at
 * @property int|null $created_by_user_id
 */
class ReportSchedule extends Model
{
    /** @use HasFactory<ReportScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'report_slug',
        'params_json',
        'format',
        'frequency',
        'day_of_week',
        'day_of_month',
        'hour',
        'recipients',
        'is_active',
        'last_run_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'params_json' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'hour' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Dispatcher runs hourly; last_run_at guards against a second send the same day.
     */
    public function isDue(CarbonImmutable $now): bool
    {
        if (! $this->is_active || $this->hour !== $now->hour) {
            return false;
        }

        $matchesDay = match ($this->frequency) {
            'daily' => true,
            'weekly' => $this->day_of_week === $now->dayOfWeek,
            'monthly' => $this->day_of_month === $now->day,
            default => false,
        };

        if (! $matchesDay) {
            return false;
        }

        return $this->last_run_at === null || $this->last_run_at->lt($now->startOfDay());
    }

    public function cadenceLabel(): string
    {
        return match ($this->frequency) {
            'daily' => sprintf('Daily at %02d:00', $this->hour),
            'weekly' => sprintf('Weekly on %s at %02d:00', self::DAY_NAMES[$this->day_of_week] ?? '?', $this->hour),
            'monthly' => sprintf('Monthly on day %d at %02d:00', $this->day_of_month, $this->hour),
            default => $this->frequency,
        };
    }

    public const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
}
