<?php

namespace App\Console\Commands;

use App\Services\Bookings\HoldExpiryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Release expired holds and promote the holds queued behind them.
 * Lower-ranked holds on the freed space+window shift up; whoever reaches 1st is emailed.
 *
 * Scheduled in routes/console.php (the #[AsScheduledTask] attribute is a no-op here).
 */
#[Signature('bookings:expire-holds')]
#[Description('Release expired holds and promote the holds queued behind them')]
class ExpireStaleHolds extends Command
{
    public function handle(HoldExpiryService $service): int
    {
        $result = $service->expireDue();

        $this->info("Released {$result['expired']} expired hold(s); promoted {$result['promoted']} queued hold(s).");

        return self::SUCCESS;
    }
}
