<?php

namespace App\Models;

use Database\Factories\SalesGoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A salesperson revenue target. `month` is null for an annual goal, 1-12 for a month.
 *
 * @property int $id
 * @property int $user_id
 * @property int $year
 * @property int|null $month
 * @property int $target_cents
 */
class SalesGoal extends Model
{
    /** @use HasFactory<SalesGoalFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'target_cents',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'target_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
