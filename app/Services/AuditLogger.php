<?php

namespace App\Services;

use App\Events\AuditEventRecorded;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Single write surface for audit_events rows.
 *
 * Request context (ip, user_agent, current user) is auto-captured when a
 * Request is available; every value is overridable so jobs/console commands
 * can record without faking a request.
 */
class AuditLogger
{
    // reentrant: while > 0, the model auto-audit hooks (Auditable::writeAuditEvent)
    // are skipped to keep bulk flows from flooding audit_events; explicit
    // record() calls are unaffected
    protected static int $suppressionDepth = 0;

    public function __construct(protected ?Request $request = null) {}

    public static function isSuppressed(): bool
    {
        return static::$suppressionDepth > 0;
    }

    /** Run $callback with model auto-auditing suppressed (reentrant). */
    public static function withoutAuditing(callable $callback): mixed
    {
        static::$suppressionDepth++;

        try {
            return $callback();
        } finally {
            static::$suppressionDepth--;
        }
    }

    /**
     * Record an audit event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        ?Model $subject = null,
        array $payload = [],
        ?User $user = null,
        ?Venue $venue = null,
    ): AuditEvent {
        // audit_events.user_id FKs users.id; non-User authenticatables (exhibitor
        // portal guard) leave it null
        $resolvedUser = $user ?? (Auth::user() instanceof User ? Auth::user() : null);

        try {
            // Build (unsaved) so the integrity HMAC can be computed over the exact
            // immutable values that will be persisted, then insert atomically with
            // the hash already set (audit_events is append-only - no later UPDATE).
            $event = new AuditEvent([
                'user_id' => $resolvedUser?->getKey(),
                'venue_id' => $venue?->getKey() ?? $this->resolveVenueId($subject),
                'event_type' => $eventType,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'ip' => $this->request?->ip(),
                'user_agent' => $this->request?->userAgent(),
                'payload_json' => $payload === [] ? null : $payload,
                'created_at' => now(),
            ]);

            // STIG APSC-DV-001350 (AU-9): cryptographically seal the row.
            $event->integrity_hash = $event->computeIntegrityHash();
            $event->save();
        } catch (\Throwable $e) {
            // STIG APSC-DV-001100 / APSC-DV-001110 (AU-5 / SI-4): an audit WRITE
            // failure is a moderate/high-impact event - emit a CRITICAL structured
            // alert (the audit channel routes CRITICAL to the SA/ISSO sink in
            // production), then re-throw so the audited action also fails loudly.
            $this->alertAuditFailure('audit.write_failure', $eventType, $e);

            throw $e;
        }

        $this->shipToLogPipeline($event);

        // seam for reacting to audited activity; best-effort, a listener failure
        // must never break the audited action
        try {
            AuditEventRecorded::dispatch($event);
        } catch (\Throwable $e) {
            report($e);
        }

        return $event;
    }

    /**
     * Mirror the event to the 'audit' log channel as JSON for off-box retention.
     * Best-effort: the audit_events row is authoritative, a logging failure must
     * never break the audited action.
     */
    protected function shipToLogPipeline(AuditEvent $event): void
    {
        try {
            Log::channel('audit')->info('audit.event', [
                'audit_id' => $event->getKey(),
                'event_type' => $event->event_type,
                'user_id' => $event->user_id,
                'venue_id' => $event->venue_id,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'ip' => $event->ip,
                'user_agent' => $event->user_agent,
                'payload' => $event->payload_json,
                'occurred_at' => Carbon::parse((string) $event->created_at)->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            // STIG APSC-DV-001100 / APSC-DV-001110 (AU-5 / SI-4): even though the
            // authoritative DB row persisted, a failure to ship the off-box mirror
            // is an audit-processing failure - alert the SA/ISSO in real time.
            $this->alertAuditFailure('audit.ship_failure', $event->event_type, $e);
        }
    }

    /**
     * Emit an immediate, CRITICAL-level structured alert for an audit
     * processing/write failure. The 'audit' log channel forwards CRITICAL
     * entries to the SA/ISSO alert sink in production (LOG_SLACK_WEBHOOK_URL /
     * SNS forwarder); see docs/audit-and-logging. STIG APSC-DV-001100 / 001110.
     */
    protected function alertAuditFailure(string $kind, string $eventType, \Throwable $e): void
    {
        try {
            Log::channel('audit')->critical($kind, [
                'failed_event_type' => $eventType,
                'exception' => $e->getMessage(),
            ]);
        } catch (\Throwable $loggingError) {
            // last resort: never let alerting itself mask the original failure
            report($loggingError);
        }
    }

    // surface subject venue_id on the row so the log filters per-venue without a join
    protected function resolveVenueId(?Model $subject): ?int
    {
        if ($subject === null) {
            return null;
        }

        if ($subject instanceof Venue) {
            return (int) $subject->getKey();
        }

        return isset($subject->venue_id) ? (int) $subject->venue_id : null;
    }
}
