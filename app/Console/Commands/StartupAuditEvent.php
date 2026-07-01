<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Record an application-startup audit event so session/application auditing is
 * demonstrably initiated when the container boots (STIG APSC-DV-000910, NIST
 * AU-14 / AU-2). Invoked by docker/entrypoint.sh after the runtime cache warm
 * and before supervisord launches php-fpm/caddy. The AuditLogger mirrors the
 * row to the `audit` log channel (JSON -> stderr -> the container log driver),
 * so a restart leaves an `app.startup` line in the off-box log stream.
 */
#[Signature('audit:startup-event')]
#[Description('Record an application-startup event in the audit trail (APSC-DV-000910)')]
class StartupAuditEvent extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $audit->record(
            eventType: 'app.startup',
            payload: [
                'role' => config('app.role'),
                'version' => config('app.version'),
                'timestamp' => now()->toIso8601String(),
            ],
        );

        $this->info('Recorded app.startup audit event.');

        return self::SUCCESS;
    }
}
