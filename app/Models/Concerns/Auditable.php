<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Adds created/updated/deleted/restored audit hooks to a model.
 *
 * Records before/after snapshots in payload_json, scrubbed against
 * auditExcludedKeys() (passwords, tokens, etc). Override auditEventPrefix()
 * to namespace the event_type per model (e.g. "venue.created").
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->writeAuditEvent('created', null, $model->getAuditableAttributes());
        });

        static::updated(function (self $model): void {
            $changed = $model->getChanges();
            if ($changed === []) {
                return;
            }

            $before = [];
            foreach (array_keys($changed) as $key) {
                $before[$key] = $model->getOriginal($key);
            }

            $model->writeAuditEvent('updated', null, [
                'before' => $model->scrubAuditPayload($before),
                'after' => $model->scrubAuditPayload($changed),
            ]);
        });

        // fires before deletion so the audit row's FK references still resolve
        static::deleting(function (self $model): void {
            $eventType = method_exists($model, 'isForceDeleting') && $model->isForceDeleting()
                ? 'force_deleted'
                : 'deleted';

            $model->writeAuditEvent($eventType, null, $model->getAuditableAttributes());
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (self $model): void {
                $model->writeAuditEvent('restored', null, $model->getAuditableAttributes());
            });
        }
    }

    public function auditEventPrefix(): string
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * Run $callback with model auditing suppressed - for bulk flows (importers)
     * that would otherwise flood audit_events; record one summary event instead.
     */
    public static function withoutAudit(callable $callback): mixed
    {
        return AuditLogger::withoutAuditing($callback);
    }

    /** Per-model hook to skip auditing a specific action. Default: audit everything. */
    public function shouldAuditAction(string $action): bool
    {
        return true;
    }

    /**
     * Attribute keys that should never appear in an audit payload.
     *
     * @return array<int, string>
     */
    public function auditExcludedKeys(): array
    {
        return ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAuditableAttributes(): array
    {
        return $this->scrubAuditPayload($this->attributesToArray());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function scrubAuditPayload(array $payload): array
    {
        foreach ($this->auditExcludedKeys() as $key) {
            unset($payload[$key]);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function writeAuditEvent(string $action, mixed $context = null, array $payload = []): void
    {
        if (AuditLogger::isSuppressed() || ! $this->shouldAuditAction($action)) {
            return;
        }

        app(AuditLogger::class)->record(
            eventType: $this->auditEventPrefix().'.'.$action,
            subject: $this,
            payload: $payload,
        );
    }
}
