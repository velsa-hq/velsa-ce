<?php

namespace App\Models;

use App\Enums\DunningStage;
use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * @property string $number
 * @property string $invoiceable_type
 * @property int $subtotal_cents
 * @property int $tax_cents
 * @property int $total_cents
 * @property int $paid_cents
 * @property Carbon|null $issued_on
 * @property Carbon|null $due_on
 * @property Carbon|null $sent_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property Carbon|null $revenue_posted_at
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use Auditable, HasFactory, Searchable;

    protected $fillable = [
        'number',
        'invoiceable_type',
        'invoiceable_id',
        'status',
        'dunning_stage',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'paid_cents',
        'issued_on',
        'due_on',
        'sent_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'net_days',
        'notes',
        'customer_reference',
        'internal_reference',
        'issued_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'dunning_stage' => DunningStage::class,
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
            'net_days' => 'integer',
            'issued_on' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'revenue_posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            if (empty($invoice->number)) {
                $year = date('Y');
                do {
                    $suffix = strtoupper(Str::random(6));
                    $candidate = "INV-{$year}-{$suffix}";
                } while (static::query()->where('number', $candidate)->exists());
                $invoice->number = $candidate;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // denormalize source display name so invoices are findable by client/exhibitor name without a join
        $sourceName = null;
        $source = $this->invoiceable;
        if ($source instanceof Booking) {
            $sourceName = trim(($source->client?->name ?? '').' '.($source->reference ?? ''));
        } elseif ($source instanceof ExhibitorOrder) {
            $sourceName = $source->exhibitor?->company_name;
        }

        return [
            'id' => (int) $this->id,
            'number' => $this->number,
            'status' => $this->status?->value,
            'source_name' => $sourceName,
            'notes' => $this->notes,
            'total_cents' => (int) $this->total_cents,
        ];
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    public function isPastDue(?\DateTimeInterface $on = null): bool
    {
        if ($this->due_on === null || $this->balanceCents() === 0) {
            return false;
        }
        $on = $on ?? now();

        return $this->due_on->isBefore($on);
    }

    public function daysPastDue(?\DateTimeInterface $on = null): int
    {
        if ($this->due_on === null) {
            return 0;
        }
        $on = $on ?? now();
        if ($this->due_on->isAfter($on)) {
            return 0;
        }

        return (int) $this->due_on->diffInDays($on);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartialPaid->value,
            InvoiceStatus::PastDue->value,
        ]);
    }

    public function scopePastDue(Builder $query, ?\DateTimeInterface $on = null): Builder
    {
        $on = $on ? $on->format('Y-m-d') : now()->toDateString();

        return $query->open()->where('due_on', '<', $on);
    }
}
