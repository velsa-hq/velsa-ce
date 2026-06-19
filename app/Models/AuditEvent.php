<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Append-only audit log row. Mutations are blocked here and by a Postgres
 * trigger; corrections go in as a new event_type='audit.correction' row.
 */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'venue_id',
        'event_type',
        'subject_type',
        'subject_id',
        'ip',
        'user_agent',
        'payload_json',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('audit_events is append-only; updates are not permitted.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('audit_events is append-only; deletes are not permitted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // masked unless the viewer holds audit.export.raw
    public const SENSITIVE_KEYS = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'tax_id', 'tax_id_encrypted', 'ssn', 'bluepay_token', 'card_number', 'cvv',
        'access_token', 'refresh_token', 'api_key', 'secret',
    ];

    /** @return array<string, mixed> */
    public function maskedPayload(): array
    {
        $payload = $this->payload_json ?? [];

        return $this->maskRecursively($payload);
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @return array<mixed, mixed>
     */
    protected function maskRecursively(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '***REDACTED***';

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->maskRecursively($value);
            }
        }

        return $data;
    }
}
