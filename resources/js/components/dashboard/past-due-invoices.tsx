import { Link } from '@inertiajs/react';

type Invoice = {
    id: number;
    number: string | null;
    status: string | null;
    total_cents: number;
    balance_cents: number;
    due_on: string | null;
    days_past_due: number;
};

type Data = {
    invoices: Invoice[];
    total_balance_cents: number;
    count: number;
};

function formatMoney(cents: number): string {
    return (
        '$' +
        (cents / 100).toLocaleString(undefined, { maximumFractionDigits: 2 })
    );
}

export function PastDueInvoices({ data }: { data: Data }) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">Past-due invoices</h2>
                <Link
                    href="/invoices"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    all invoices
                </Link>
            </div>

            {data.invoices.length === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    Nothing past due - clean A/R.
                </p>
            ) : (
                <>
                    <ul className="flex flex-col gap-1">
                        {data.invoices.map((inv) => (
                            <li
                                key={inv.id}
                                className="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted"
                            >
                                <Link
                                    href={`/invoices/${inv.id}`}
                                    className="flex-1 truncate font-mono text-xs font-medium hover:underline"
                                >
                                    {inv.number ?? `#${inv.id}`}
                                </Link>
                                <span className="text-[11px] text-muted-foreground">
                                    due {inv.due_on ?? '-'}
                                </span>
                                <span className="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-medium tracking-wider text-rose-900 uppercase dark:bg-rose-900/40 dark:text-rose-100">
                                    {inv.days_past_due}d late
                                </span>
                                <span className="w-24 text-right font-mono text-[11px] tabular-nums">
                                    {formatMoney(inv.balance_cents)}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-1 flex items-center justify-between border-t border-sidebar-border/40 pt-2 text-xs">
                        <span className="text-muted-foreground">
                            {data.count} open · total balance
                        </span>
                        <span className="font-mono font-semibold text-rose-700 tabular-nums dark:text-rose-300">
                            {formatMoney(data.total_balance_cents)}
                        </span>
                    </div>
                </>
            )}
        </div>
    );
}
