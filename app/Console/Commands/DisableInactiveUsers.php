<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Disable accounts inactive beyond the threshold (default 35 days).
 * STIG APSC-DV-000320 / SRG-APP-000705 (NIST AC-2(3)). Inactivity comes from
 * last_active_at (UpdateLastActiveMiddleware). Sets disabled_reason (login is
 * blocked by FortifyServiceProvider) and stamps force_logout_at to kill live
 * sessions. Scheduled in routes/console.php.
 */
#[Signature('users:disable-inactive')]
#[Description('Disable accounts inactive beyond the configured threshold')]
class DisableInactiveUsers extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $days = (int) config('auth.inactivity_disable_days', 35);

        if ($days <= 0) {
            $this->info('Inactivity auto-disable is off (auth.inactivity_disable_days <= 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $users = User::query()
            ->whereNull('disabled_reason')
            ->whereNotNull('last_active_at')
            ->where('last_active_at', '<', $cutoff)
            ->get();

        foreach ($users as $user) {
            $user->forceFill([
                'disabled_reason' => 'auto:inactivity',
                'force_logout_at' => now(),
            ])->save();

            $audit->record(
                eventType: 'user.disabled',
                subject: $user,
                payload: ['reason' => 'auto:inactivity', 'inactive_days' => $days],
            );
        }

        $this->info("Disabled {$users->count()} inactive account(s) (threshold: {$days} days).");

        return self::SUCCESS;
    }
}
