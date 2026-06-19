import { Head, Link, router } from '@inertiajs/react';

type LedgerRow = {
    id: number;
    posted_on: string | null;
    description: string;
    debit_cents: number;
    credit_cents: number;
    running_cents: number;
    venue_name: string | null;
    is_manual: boolean;
    is_reversal: boolean;
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    account: {
        code: string;
        name: string;
        type_label: string | null;
        normal_balance: string | null;
    };
    opening_cents: number;
    closing_cents: number;
    entries: LedgerRow[];
    venues: VenueOption[];
    filters: {
        venue_id: number | null;
        from: string | null;
        to: string | null;
    };
};

function money(cents: number): string {
    return (Math.abs(cents) / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    });
}

/** Signed (debit-positive) cents -> balance in its natural Dr/Cr direction. */
function balance(cents: number): string {
    if (cents === 0) {
        return '$0.00';
    }

    return `${money(cents)} ${cents > 0 ? 'Dr' : 'Cr'}`;
}

export default function AccountLedger({
    account,
    opening_cents,
    closing_cents,
    entries,
    venues,
    filters,
}: Props) {
    const onFilter = (key: 'venue_id' | 'from' | 'to', value: string) => {
        const params: Record<string, string> = {};

        if (filters.venue_id) {
            params.venue_id = String(filters.venue_id);
        }

        if (filters.from) {
            params.from = filters.from;
        }

        if (filters.to) {
            params.to = filters.to;
        }

        params[key] = value;

        if (!value) {
            delete params[key];
        }

        router.get(`/accounting/accounts/${account.code}`, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`${account.code} · Ledger`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header
                    className="flex flex-wrap items-end justify-between gap-3"
                    data-tour-id="account-ledger"
                >
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            <span className="font-mono">{account.code}</span> ·{' '}
                            {account.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {account.type_label}
                            {account.normal_balance
                                ? ` · ${account.normal_balance}-normal`
                                : ''}{' '}
                            · closing balance{' '}
                            <span className="font-medium text-foreground">
                                {balance(closing_cents)}
                            </span>
                        </p>
                    </div>
                    <Link
                        href="/accounting"
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Journal
                    </Link>
                </header>

                <div className="flex flex-wrap items-center gap-3 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <label className="flex items-center gap-1.5 text-sm">
                        From
                        <input
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(e) => onFilter('from', e.target.value)}
                            className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                        />
                    </label>
                    <label className="flex items-center gap-1.5 text-sm">
                        To
                        <input
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(e) => onFilter('to', e.target.value)}
                            className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                        />
                    </label>
                    <select
                        value={filters.venue_id ?? ''}
                        onChange={(e) => onFilter('venue_id', e.target.value)}
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                    >
                        <option value="">All venues</option>
                        {venues.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Description
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Venue
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Debit
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Credit
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr className="border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60">
                                <td
                                    className="px-3 py-2 text-xs text-muted-foreground italic"
                                    colSpan={5}
                                >
                                    Opening balance
                                </td>
                                <td className="px-3 py-2 text-right font-mono text-xs">
                                    {balance(opening_cents)}
                                </td>
                            </tr>
                            {entries.map((e) => (
                                <tr
                                    key={e.id}
                                    className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                >
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {e.posted_on}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {e.description}
                                        {e.is_reversal ? (
                                            <span className="ml-1 text-[10px] text-muted-foreground">
                                                (reversal)
                                            </span>
                                        ) : e.is_manual ? (
                                            <span className="ml-1 text-[10px] text-muted-foreground">
                                                (manual)
                                            </span>
                                        ) : null}
                                    </td>
                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                        {e.venue_name ?? '-'}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono text-xs">
                                        {e.debit_cents > 0
                                            ? money(e.debit_cents)
                                            : '-'}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono text-xs">
                                        {e.credit_cents > 0
                                            ? money(e.credit_cents)
                                            : '-'}
                                    </td>
                                    <td className="px-3 py-2 text-right font-mono text-xs">
                                        {balance(e.running_cents)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="border-t-2 border-border bg-muted/40 font-semibold">
                            <tr>
                                <td className="px-3 py-2" colSpan={5}>
                                    Closing balance
                                </td>
                                <td className="px-3 py-2 text-right font-mono">
                                    {balance(closing_cents)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </>
    );
}

AccountLedger.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Account ledger', href: '#' },
    ],
};
