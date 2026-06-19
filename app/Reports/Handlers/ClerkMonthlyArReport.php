<?php

namespace App\Reports\Handlers;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\ExhibitorOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Services\Accounting\ValueFormatter;
use Carbon\CarbonImmutable;

/**
 * Monthly AR summary for hand-off to the Clerk of Court: invoices issued in the
 * month, period-end AR position, and an aging snapshot. Movement totals come
 * from journal postings against AR (1100) and bad-debt (5900) so the figures
 * reconcile to the same GL the Clerk balances against.
 */
class ClerkMonthlyArReport implements ReportHandler
{
    public function slug(): string
    {
        return 'clerk-monthly-ar';
    }

    public function category(): string
    {
        return 'Accounting';
    }

    public function title(): string
    {
        return 'Clerk of Court - Monthly AR';
    }

    public function description(): string
    {
        return 'Monthly AR summary for hand-off to the Clerk of Court: invoices issued in the period, movement totals, period-end balance, and aging snapshot.';
    }

    public function parameters(): array
    {
        return [
            [
                'key' => 'month',
                'label' => 'Month',
                'type' => 'month',
                'default' => now()->subMonthNoOverflow()->format('Y-m'),
            ],
        ];
    }

    public function run(array $params): ReportResult
    {
        $monthStr = isset($params['month'])
            ? (string) $params['month']
            : now()->subMonthNoOverflow()->format('Y-m');
        $month = CarbonImmutable::createFromFormat('Y-m', $monthStr)
            ?: now()->subMonthNoOverflow()->startOfMonth();
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        // -- Rows: invoices issued in the period -----------------
        $invoices = Invoice::query()
            ->with(['invoiceable'])
            ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('issued_on')
            ->orderBy('id')
            ->get();

        $rows = $invoices->map(function (Invoice $inv) {
            return [
                'issued_on' => $inv->issued_on?->format('M j, Y'),
                'number' => $inv->number,
                'source' => $this->sourceLabel($inv),
                'status' => $inv->status?->label() ?? '-',
                'total' => ValueFormatter::usd($inv->total_cents),
                'paid' => ValueFormatter::usd($inv->paid_cents),
                'balance' => ValueFormatter::usd($inv->balanceCents()),
            ];
        })->all();

        // -- Movement totals from journal entries on AR (1100) ---
        $arDebits = (int) JournalEntry::query()
            ->where('account_code', '1100')
            ->whereBetween('posted_on', [$from->toDateString(), $to->toDateString()])
            ->sum('debit_cents');
        $arCredits = (int) JournalEntry::query()
            ->where('account_code', '1100')
            ->whereBetween('posted_on', [$from->toDateString(), $to->toDateString()])
            ->sum('credit_cents');
        $writeOffs = (int) JournalEntry::query()
            ->where('account_code', '5900')
            ->whereBetween('posted_on', [$from->toDateString(), $to->toDateString()])
            ->sum('debit_cents');

        // -- Period-end AR position + aging ----------------------
        $openAtPeriodEnd = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::PartialPaid->value,
                InvoiceStatus::PastDue->value,
            ])
            ->where('issued_on', '<=', $to->toDateString())
            ->get();

        $periodEndArCents = (int) $openAtPeriodEnd
            ->sum(fn (Invoice $i) => max(0, $i->total_cents - $i->paid_cents));

        $aging = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
        foreach ($openAtPeriodEnd as $inv) {
            $balance = max(0, $inv->total_cents - $inv->paid_cents);
            if ($balance <= 0 || $inv->due_on === null) {
                $aging['current'] += $balance;

                continue;
            }
            $daysPastDue = (int) $inv->due_on->diffInDays($to, false);
            if ($daysPastDue <= 0) {
                $aging['current'] += $balance;
            } elseif ($daysPastDue <= 30) {
                $aging['1_30'] += $balance;
            } elseif ($daysPastDue <= 60) {
                $aging['31_60'] += $balance;
            } elseif ($daysPastDue <= 90) {
                $aging['61_90'] += $balance;
            } else {
                $aging['over_90'] += $balance;
            }
        }

        $summary = [
            ['label' => 'New invoices', 'value' => (string) $invoices->count()],
            ['label' => 'Total invoiced this period', 'value' => ValueFormatter::usd($invoices->sum('total_cents'))],
            ['label' => 'AR debits (issued + refunds)', 'value' => ValueFormatter::usd($arDebits)],
            ['label' => 'AR credits (payments + write-offs)', 'value' => ValueFormatter::usd($arCredits)],
            ['label' => 'Write-offs', 'value' => ValueFormatter::usd($writeOffs)],
            ['label' => 'AR balance at period end', 'value' => ValueFormatter::usd($periodEndArCents)],
            ['label' => 'Aged 1-30', 'value' => ValueFormatter::usd($aging['1_30'])],
            ['label' => 'Aged 31-60', 'value' => ValueFormatter::usd($aging['31_60'])],
            ['label' => 'Aged 61-90', 'value' => ValueFormatter::usd($aging['61_90'])],
            ['label' => 'Aged 90+', 'value' => ValueFormatter::usd($aging['over_90'])],
        ];

        return new ReportResult(
            title: $this->title(),
            description: $month->format('F Y').' - '.$from->format('M j').' through '.$to->format('M j, Y'),
            columns: [
                ['key' => 'issued_on', 'label' => 'Issued'],
                ['key' => 'number', 'label' => 'Invoice #'],
                ['key' => 'source', 'label' => 'Source'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'total', 'label' => 'Total', 'align' => 'right'],
                ['key' => 'paid', 'label' => 'Paid', 'align' => 'right'],
                ['key' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ],
            rows: $rows,
            summary: $summary,
            generatedAt: now()->toIso8601String(),
        );
    }

    protected function sourceLabel(Invoice $invoice): string
    {
        $source = $invoice->invoiceable;
        if ($source instanceof ExhibitorOrder) {
            return 'Order '.$source->order_number;
        }
        if ($source instanceof Booking) {
            return 'Booking '.$source->reference;
        }

        return class_basename((string) $invoice->invoiceable_type).' #'.$invoice->invoiceable_id;
    }
}
