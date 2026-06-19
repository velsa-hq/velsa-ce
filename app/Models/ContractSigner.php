<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ContractSignerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractSigner extends Model
{
    /** @use HasFactory<ContractSignerFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'contract_id',
        'signing_order',
        'role',
        'name',
        'email',
        'provider_recipient_id',
        'viewed_at',
        'signed_at',
        'declined_at',
        'decline_reason',
    ];

    protected function casts(): array
    {
        return [
            'signing_order' => 'integer',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function hasSigned(): bool
    {
        return $this->signed_at !== null;
    }
}
