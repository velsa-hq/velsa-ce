<?php

namespace Database\Seeders;

use App\Models\ExhibitorOrder;
use App\Services\Accounting\InvoiceService;
use Illuminate\Database\Seeder;

/**
 * Ensure an invoice exists for every billable exhibitor order.
 * Idempotent; run after the exhibitor seeders.
 */
class InvoicesBackfillSeeder extends Seeder
{
    public function run(InvoiceService $invoices): void
    {
        ExhibitorOrder::query()
            ->where('total_cents', '>', 0)
            ->chunkById(200, function ($batch) use ($invoices) {
                foreach ($batch as $order) {
                    $invoices->issueForOrder($order);
                    $invoices->refreshFromSource($order->invoice ?? $order->fresh()->invoice);
                }
            });
    }
}
