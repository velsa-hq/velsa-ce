<?php

namespace App\Console\Commands;

use App\Services\Reports\ScheduledReportDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Email report schedules due this hour. Runs hourly because schedules fire on a
 * specific hour-of-day. Scheduled in routes/console.php.
 */
#[Signature('reports:dispatch-scheduled')]
#[Description('Email report schedules that are due this hour')]
class DispatchScheduledReports extends Command
{
    public function handle(ScheduledReportDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatchDue(CarbonImmutable::now());

        $this->info("Dispatched {$result['dispatched']} scheduled report(s); skipped {$result['skipped']}, failed {$result['failed']}.");

        return self::SUCCESS;
    }
}
