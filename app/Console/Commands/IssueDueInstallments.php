<?php

namespace App\Console\Commands;

use App\Models\Installment;
use App\Services\Accounting\InvoiceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Issue an invoice for any installment whose due_date has arrived and that
 * has no invoice yet. Invoice creation drives the issued-invoice audit +
 * narrative observers; the client email rides the existing Booking-invoice flow.
 */
#[Signature('installments:issue-due')]
#[Description('Issue invoices for any installments whose due_date has arrived')]
class IssueDueInstallments extends Command
{
    public function handle(InvoiceService $invoices): int
    {
        $today = now()->toDateString();
        $issued = 0;

        Installment::query()
            ->whereNull('invoice_id')
            ->whereDate('due_date', '<=', $today)
            ->with('schedule.booking')
            ->chunkById(100, function ($batch) use ($invoices, &$issued) {
                foreach ($batch as $installment) {
                    $booking = $installment->schedule?->booking;
                    if ($booking === null) {
                        continue;
                    }

                    $invoice = $invoices->issueInstallmentForBooking($booking, $installment);

                    $installment->update([
                        'invoice_id' => $invoice->id,
                        'invoiced_at' => now(),
                    ]);

                    $issued++;
                }
            });

        $this->info("IssueDueInstallments: issued {$issued} installment invoice(s).");

        return self::SUCCESS;
    }
}
