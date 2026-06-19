<?php

namespace App\Models;

use Database\Factories\LedgerExportBatchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $period
 * @property string $status
 * @property int $entry_count
 * @property int $debit_total_cents
 * @property int $credit_total_cents
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property string|null $delivery_transport
 * @property string|null $delivery_detail
 * @property string|null $failure_reason
 * @property-read User|null $creator
 * @property-read User|null $voidedBy
 * @property-read ExportTemplate|null $template
 */
class LedgerExportBatch extends Model
{
    /** @use HasFactory<LedgerExportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'period',
        'status',
        'entry_count',
        'debit_total_cents',
        'credit_total_cents',
        'export_template_id',
        'file_s3_key',
        'delivery_transport',
        'delivery_detail',
        'sent_at',
        'acknowledged_at',
        'voided_at',
        'void_reason',
        'voided_by_user_id',
        'failure_reason',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_count' => 'integer',
            'debit_total_cents' => 'integer',
            'credit_total_cents' => 'integer',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'export_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ExportTemplate::class, 'export_template_id');
    }

    public function isBalanced(): bool
    {
        return $this->debit_total_cents === $this->credit_total_cents;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
