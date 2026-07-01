<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Record an application-shutdown audit event so orderly shutdowns are captured
 * in the audit trail (STIG APSC-DV-000940, NIST AU-14 / AU-2). Invoked by the
 * SIGTERM/SIGINT trap in docker/entrypoint.sh when the orchestrator drains the
 * task. The AuditLogger mirrors the row to the `audit` log channel
 * (JSON -> stderr -> the container log driver), so the shutdown is visible
 * off-box before the container exits.
 */
#[Signature('audit:shutdown-event {--signal=SIGTERM : The signal that triggered shutdown}')]
#[Description('Record an application-shutdown event in the audit trail (APSC-DV-000940)')]
class ShutdownAuditEvent extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $audit->record(
            eventType: 'app.shutdown',
            payload: [
                'role' => config('app.role'),
                'signal' => (string) $this->option('signal'),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        $this->info('Recorded app.shutdown audit event.');

        return self::SUCCESS;
    }
}
