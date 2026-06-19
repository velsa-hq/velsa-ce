<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Accounting\InvoiceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Advances dunning_stage on open invoices by days-past-due; InvoiceService
 * sends the matching dunning email as each stage advances. Scheduled in
 * routes/console.php.
 */
#[Signature('invoices:advance-dunning')]
#[Description('Advance dunning_stage on open invoices by days-past-due')]
class AdvanceDunning extends Command
{
    public function handle(InvoiceService $invoices): int
    {
        $count = 0;
        $advanced = 0;

        Invoice::query()->open()->chunkById(200, function ($batch) use ($invoices, &$count, &$advanced) {
            foreach ($batch as $invoice) {
                $count++;
                if ($invoices->advanceDunning($invoice) !== null) {
                    $advanced++;
                }
            }
        });

        $this->info("Scanned {$count} open invoices; advanced {$advanced} dunning stage(s).");

        return self::SUCCESS;
    }
}
