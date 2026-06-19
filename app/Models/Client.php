<?php

namespace App\Models;

use App\Enums\ClientType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDocuments;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\MediaLibrary\HasMedia;

class Client extends Model implements HasMedia
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasDocuments, HasFactory, Searchable, SoftDeletes;

    const DELETED_AT = 'retired_at';

    protected $fillable = [
        'name',
        'type',
        'industry',
        'source',
        'primary_contact_id',
        'address_json',
        'tax_id_encrypted',
        'notes',
        'custom_fields_json',
        'retired_at',
    ];

    protected $hidden = [
        'tax_id_encrypted',
    ];

    public function auditExcludedKeys(): array
    {
        return ['tax_id_encrypted'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'industry' => $this->industry,
            'source' => $this->source,
            'notes' => $this->notes,
        ];
    }

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
            'address_json' => 'array',
            'tax_id_encrypted' => 'encrypted',
            'custom_fields_json' => 'array',
            'retired_at' => 'datetime',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return MorphMany<InsuranceCertificate, $this> */
    public function insuranceCertificates(): MorphMany
    {
        return $this->morphMany(InsuranceCertificate::class, 'holder');
    }
}
