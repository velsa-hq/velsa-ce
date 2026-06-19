<?php

namespace App\Models;

use App\Models\Concerns\IsVenueScoped;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Single-leg journal entry. Append-only in the app and via a Postgres
 * trigger; reversals are new rows pointing at the original through
 * reversed_entry_id, never edits. Create via JournalEntry::post().
 * No Auditable trait - the append-only constraints make it redundant.
 *
 * @property int $id
 * @property string $account_code
 * @property string $description
 * @property int $debit_cents
 * @property int $credit_cents
 * @property Carbon|null $posted_on
 * @property string|null $entry_group
 * @property int|null $reversed_entry_id
 * @property-read Venue|null $venue
 */
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory, IsVenueScoped;

    public $timestamps = false;

    protected $fillable = [
        'venue_id',
        'source_type',
        'source_id',
        'reversed_entry_id',
        'entry_group',
        'export_batch_id',
        'chart_of_account_id',
        'account_code',
        'fund_id',
        'fund_code',
        'description',
        'debit_cents',
        'credit_cents',
        'posted_on',
        'posted_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
            'posted_on' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JournalEntry $entry): void {
            $entry->syncAccountAndFundReferences();
        });

        static::updating(function (JournalEntry $entry): void {
            // only export_batch_id may change after insert; mirrors the Postgres trigger
            $dirty = $entry->getDirty();
            unset($dirty['export_batch_id']);
            if ($dirty !== []) {
                throw new RuntimeException('Journal entries are immutable. Create a reversing entry instead.');
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Journal entries are append-only; deletion is forbidden.');
        });
    }

    /**
     * Resolve FK columns (source of truth) and denormalized code columns
     * in both directions. Throws on an unresolvable code so the FK
     * constraint never fires with a confusing message.
     */
    protected function syncAccountAndFundReferences(): void
    {
        $account = null;
        if ($this->chart_of_account_id !== null) {
            $account = ChartOfAccount::query()->find($this->chart_of_account_id);
        } elseif (! empty($this->account_code)) {
            $account = ChartOfAccount::query()->where('code', $this->account_code)->first();
            if ($account === null) {
                throw new RuntimeException("Account code '{$this->account_code}' does not exist in the chart of accounts.");
            }
            $this->chart_of_account_id = $account->id;
        }

        if ($account !== null) {
            $this->account_code = $account->code;

            // roll-up accounts are never posted to; retired accounts reject new entries
            if (! $account->isPostable()) {
                throw new RuntimeException("Account '{$account->code}' is a roll-up (is_postable=false); journal entries must reference a leaf-level account.");
            }
            $postedOn = $this->posted_on;
            if ($postedOn !== null && ! $account->isActive($postedOn)) {
                throw new RuntimeException("Account '{$account->code}' is not active on {$postedOn->format('Y-m-d')}.");
            }
        }

        // fund is optional; system-level entries may not be fund-scoped
        $fund = null;
        if ($this->fund_id !== null) {
            $fund = Fund::query()->find($this->fund_id);
        } elseif (! empty($this->fund_code)) {
            $fund = Fund::query()->where('code', $this->fund_code)->first();
            if ($fund === null) {
                throw new RuntimeException("Fund code '{$this->fund_code}' does not exist.");
            }
            $this->fund_id = $fund->id;
        }

        if ($fund !== null) {
            $this->fund_code = $fund->code;
            $postedOn = $this->posted_on;
            if ($postedOn !== null && ! $fund->isActive($postedOn)) {
                throw new RuntimeException("Fund '{$fund->code}' is not active on {$postedOn->format('Y-m-d')}.");
            }
        }
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function exportBatch(): BelongsTo
    {
        return $this->belongsTo(LedgerExportBatch::class, 'export_batch_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function scopeUnexported(Builder $query): Builder
    {
        return $query->whereNull('export_batch_id');
    }

    /**
     * Create an entry, defaulting posted_on to today.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function post(array $attrs): self
    {
        return static::query()->create(array_merge([
            'posted_on' => now()->toDateString(),
            'created_at' => now(),
            'debit_cents' => 0,
            'credit_cents' => 0,
        ], $attrs));
    }
}
