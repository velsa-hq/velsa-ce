<?php

namespace App\Dashboard\Tiles;

use App\Dashboard\DashboardTile;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class PastDueInvoicesTile extends DashboardTile
{
    public function key(): string
    {
        return 'past_due_invoices';
    }

    public function label(): string
    {
        return 'Past-due invoices';
    }

    public function description(): string
    {
        return 'Invoices past their due date with an outstanding balance, ordered by oldest first.';
    }

    public function columnSpan(): int
    {
        return 6;
    }

    public function permission(): ?string
    {
        return 'accounting.view';
    }

    public function render(User $user): array
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::PastDue->value)
            ->orderBy('due_on')
            ->limit(10)
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'status' => $i->status?->value,
                'total_cents' => (int) $i->total_cents,
                'balance_cents' => $i->balanceCents(),
                'due_on' => $i->due_on?->toDateString(),
                'days_past_due' => $i->daysPastDue(),
            ])
            ->all();

        $totalBalance = array_sum(array_column($invoices, 'balance_cents'));

        return [
            'invoices' => $invoices,
            'total_balance_cents' => $totalBalance,
            'count' => count($invoices),
        ];
    }
}
