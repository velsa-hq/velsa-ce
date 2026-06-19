<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Expire contracts sent for signature but not completed within the window.
 * Only Sent/Viewed are eligible; partially-signed/signed/declined/voided are left alone.
 *
 * Scheduled in routes/console.php (the #[AsScheduledTask] attribute is a no-op here).
 */
#[Signature('contracts:expire-stale')]
#[Description('Expire sent/viewed contracts older than the configured window')]
class ExpireStaleContracts extends Command
{
    public function handle(SystemSettings $settings): int
    {
        $days = (int) $settings->get('defaults.contract_expiry_after_days', 30);

        $expired = Contract::query()
            ->whereIn('status', [ContractStatus::Sent->value, ContractStatus::Viewed->value])
            ->whereNotNull('sent_at')
            ->where('sent_at', '<', now()->subDays($days))
            ->update([
                'status' => ContractStatus::Expired->value,
                'expired_at' => now(),
            ]);

        $this->info("Expired {$expired} stale contract(s) (window: {$days} days).");

        return self::SUCCESS;
    }
}
