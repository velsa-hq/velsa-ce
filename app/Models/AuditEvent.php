<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
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
        'integrity_hash',
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

    /**
     * HMAC-SHA256 over the canonical serialization of the immutable fields,
     * keyed by an APP_KEY-derived sub-key. Single source of truth for both the
     * write path (AuditLogger) and the verifier (audit:verify-integrity), so a
     * stored hash and a recomputed one agree byte-for-byte. STIG APSC-DV-001350.
     */
    public function computeIntegrityHash(): string
    {
        $canonical = json_encode([
            'event_type' => $this->event_type,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'user_id' => $this->user_id,
            'venue_id' => $this->venue_id,
            'ip' => $this->ip,
            'payload_json' => $this->payload_json,
            'created_at' => Carbon::parse((string) $this->created_at)->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $key = hash_hmac('sha256', 'audit-events.integrity', (string) config('app.key'));

        return hash_hmac('sha256', (string) $canonical, $key);
    }

    /** Whether the stored hash matches a freshly recomputed one (unmodified row). */
    public function hasValidIntegrityHash(): bool
    {
        return $this->integrity_hash !== null
            && hash_equals($this->integrity_hash, $this->computeIntegrityHash());
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
