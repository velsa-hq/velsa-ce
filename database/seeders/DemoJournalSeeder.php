<?php

namespace Database\Seeders;

use App\Models\ExportTemplate;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\Venue;
use App\Services\Accounting\LedgerExporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a balanced general ledger for the demo (the invoice/payment
 * seeders don't post to the ledger). Every transaction balances
 * internally so the trial balance balances by construction; a few
 * entries are manual (entry_group set) to exercise the manual badge
 * and Reverse affordance. Append-only: no-ops if entries exist.
 */
class DemoJournalSeeder extends Seeder
{
    public function run(): void
    {
        // key idempotency on this seeder's own opening-balance marker:
        // invoice issuance already posts entries before we run, so a bare
        // "any entries exist" guard would always skip
        if (JournalEntry::query()->where('description', 'Opening balances - FY26')->exists()) {
            $this->command->warn('DemoJournalSeeder: demo ledger already seeded - skipping (append-only).');

            return;
        }

        $venues = Venue::query()->pluck('id', 'slug');

        $aquila = $venues['aquila-performing-arts-hall'] ?? null;
        $driftwood = $venues['driftwood-fairgrounds'] ?? null;

        /**
         * Post one balanced transaction as a set of legs.
         *
         * @param  array<int, array{0: string, 1: 'd'|'c', 2: int}>  $legs
         */
        $tx = function (
            string $date,
            string $description,
            array $legs,
            ?int $venueId = null,
            ?string $fundCode = null,
            ?string $group = null,
        ): void {
            foreach ($legs as [$code, $side, $cents]) {
                JournalEntry::post([
                    'account_code' => $code,
                    'description' => $description,
                    'posted_on' => $date,
                    'venue_id' => $venueId,
                    'fund_code' => $fundCode,
                    'entry_group' => $group,
                    $side === 'd' ? 'debit_cents' : 'credit_cents' => $cents,
                ]);
            }
        };

        // opening balances
        $tx('2026-02-01', 'Opening balances - FY26', [
            ['1010', 'd', 25_000_000],
            ['1020', 'd', 10_000_000],
            ['3010', 'c', 35_000_000],
        ], fundCode: 'GENERAL');

        $tx('2026-02-01', 'Prepaid annual insurance premium', [
            ['1200', 'd', 1_200_000],
            ['1010', 'c', 1_200_000],
        ], fundCode: 'GENERAL');

        // revenue + A/R come from real invoices (InvoiceService posts the
        // issuance accrual); the cash-receipts loop below clears collected A/R

        // operating expenses
        $tx('2026-02-28', 'February payroll', [
            ['5100', 'd', 3_800_000],
            ['1010', 'c', 3_800_000],
        ], fundCode: 'GENERAL');

        $tx('2026-03-05', 'February utilities', [
            ['5200', 'd', 420_000],
            ['1010', 'c', 420_000],
        ], fundCode: 'GENERAL');

        $tx('2026-03-15', 'Janitorial & event supplies (on account)', [
            ['5400', 'd', 260_000],
            ['2010', 'c', 260_000],
        ], fundCode: 'GENERAL');

        $tx('2026-04-01', 'Pay supplier - A/P settlement', [
            ['2010', 'd', 260_000],
            ['1010', 'c', 260_000],
        ], fundCode: 'GENERAL');

        $tx('2026-03-22', 'Tourism marketing campaign', [
            ['5600', 'd', 550_000],
            ['1010', 'c', 550_000],
        ], fundCode: 'TOURISM');

        $tx('2026-04-03', 'Facility repairs & maintenance', [
            ['5300', 'd', 190_000],
            ['1010', 'c', 190_000],
        ], $driftwood, 'GENERAL');

        $tx('2026-04-10', 'Merchant processing fees - Q1', [
            ['5700', 'd', 125_000],
            ['1010', 'c', 125_000],
        ], fundCode: 'GENERAL');

        // cash receipts on collected invoices: payments were seeded as
        // paid_cents without posting cash, so clear A/R for collected
        // amounts; unpaid balances stay outstanding in A/R
        Invoice::query()
            ->where('paid_cents', '>', 0)
            ->get()
            ->each(fn (Invoice $invoice) => $tx(
                $invoice->paid_at?->toDateString()
                    ?? $invoice->issued_on?->toDateString()
                    ?? '2026-05-01',
                "Payment received - invoice {$invoice->number}",
                [
                    ['1010', 'd', (int) $invoice->paid_cents],
                    ['1100', 'c', (int) $invoice->paid_cents],
                ],
            ));

        // manual month-end entries (entry_group set), posted last and dated
        // today so they sit at the top of the journal's first page
        $today = now()->toDateString();
        $tx($today, 'Utilities accrual (month-end)', [
            ['5200', 'd', 140_000],
            ['2010', 'c', 140_000],
        ], fundCode: 'GENERAL', group: (string) Str::uuid());

        $tx($today, 'Insurance amortization', [
            ['5800', 'd', 100_000],
            ['1200', 'c', 100_000],
        ], fundCode: 'GENERAL', group: (string) Str::uuid());

        $tx($today, 'Reclassify misc receipts to venue rental', [
            ['4900', 'd', 120_000],
            ['4100', 'c', 120_000],
        ], $aquila, 'GENERAL', group: (string) Str::uuid());

        // export the opening period into a batch so the Batch column and
        // export-batch cards aren't empty; Feb gets claimed, Mar-May stay
        // pending, mirroring a real mid-cycle ledger
        if (ExportTemplate::resolveDefault() !== null) {
            $creatorId = User::query()->orderBy('id')->value('id');
            app(LedgerExporter::class)->exportPeriod('2026-02', $creatorId);
        }

        $count = JournalEntry::query()->count();
        $claimed = JournalEntry::query()->whereNotNull('export_batch_id')->count();
        $this->command->info("DemoJournalSeeder: posted {$count} legs ({$claimed} exported into a batch).");
    }
}
