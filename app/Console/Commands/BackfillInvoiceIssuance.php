<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\Accounting\InvoiceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Idempotent backfill of the issuance accrual (debit A/R, credit revenue + tax).
 * Skips drafts and voids; includes write-offs so their bad-debt A/R credit has a
 * matching debit.
 */
#[Signature('accounting:backfill-issuance {--dry-run : Report how many invoices would be posted without posting}')]
#[Description('Post the issuance accrual for invoices that predate accrual revenue recognition')]
class BackfillInvoiceIssuance extends Command
{
    public function handle(InvoiceService $service): int
    {
        $query = Invoice::query()
            ->whereNull('revenue_posted_at')
            ->where('total_cents', '>', 0)
            ->whereNotIn('status', [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Void->value,
            ]);

        if ($this->option('dry-run')) {
            $this->info("Would post issuance for {$query->count()} invoice(s).");

            return self::SUCCESS;
        }

        $posted = 0;
        $query->chunkById(200, function ($invoices) use ($service, &$posted) {
            foreach ($invoices as $invoice) {
                if ($service->postIssuanceFor($invoice)) {
                    $posted++;
                }
            }
        });

        $this->info("Posted issuance for {$posted} invoice(s).");

        return self::SUCCESS;
    }
}
