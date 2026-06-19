<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Archives closed leads older than the configured window. Leads with a
 * future event date stay on the board regardless of close age. Window is
 * the defaults.pipeline_archive_after_days setting (default 60). Scheduled
 * daily in routes/console.php.
 */
#[Signature('pipeline:archive-stale')]
#[Description('Archive closed leads older than the configured pipeline window')]
class ArchiveStaleLeads extends Command
{
    public function handle(SystemSettings $settings): int
    {
        $afterDays = (int) $settings->get('defaults.pipeline_archive_after_days', 60);

        $archived = Lead::query()
            ->archivable($afterDays)
            ->update(['archived_at' => now()]);

        $this->info("Archived {$archived} stale lead(s) (window: {$afterDays} days).");

        return self::SUCCESS;
    }
}
