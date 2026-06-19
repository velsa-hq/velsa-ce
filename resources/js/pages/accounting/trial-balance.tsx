import { Head, Link, router } from '@inertiajs/react';

type Row = {
    account_code: string;
    name: string | null;
    type_label: string | null;
    debit_balance_cents: number;
    credit_balance_cents: number;
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    rows: Row[];
    debit_total_cents: number;
    credit_total_cents: number;
    balanced: boolean;
    venues: VenueOption[];
    filters: { venue_id: number | null; as_of: string };
};

function money(cents: number): string {
    return cents === 0
        ? '-'
        : (cents / 100).toLocaleString(undefined, {
              style: 'currency',
              currency: 'USD',
              minimumFractionDigits: 2,
          });
}

export default function TrialBalance({
    rows,
    debit_total_cents,
    credit_total_cents,
    balanced,
    venues,
    filters,
}: Props) {
    const onFilter = (key: 'venue_id' | 'as_of', value: string) => {
        const params: Record<string, string> = { as_of: filters.as_of };

        if (filters.venue_id) {
            params.venue_id = String(filters.venue_id);
        }

        params[key] = value;

        if (!value) {
            delete params[key];
        }

        router.get('/accounting/trial-balance', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Trial balance" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header
                    className="flex flex-wrap items-end justify-between gap-3"
                    data-tour-id="trial-balance"
                >
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Trial balance
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            As of {filters.as_of} ·{' '}
                            {balanced ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-300">
                                    balanced
                                </span>
                            ) : (
                                <span className="font-medium text-rose-700 dark:text-rose-300">
                                    out of balance
                                </span>
                            )}
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
                        As of
                        <input
                            type="date"
                            value={filters.as_of}
                            onChange={(e) => onFilter('as_of', e.target.value)}
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
                                    Account
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Type
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Debit
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Credit
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No activity through this date.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((r) => (
                                    <tr
                                        key={r.account_code}
                                        className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/accounting/accounts/${r.account_code}`}
                                                className="font-mono text-xs hover:underline"
                                            >
                                                {r.account_code}
                                            </Link>
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                {r.name}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {r.type_label ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {money(r.debit_balance_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {money(r.credit_balance_cents)}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                        <tfoot className="border-t-2 border-border bg-muted/40 font-semibold">
                            <tr>
                                <td className="px-3 py-2" colSpan={2}>
                                    Totals
                                </td>
                                <td className="px-3 py-2 text-right font-mono">
                                    {money(debit_total_cents)}
                                </td>
                                <td className="px-3 py-2 text-right font-mono">
                                    {money(credit_total_cents)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </>
    );
}

TrialBalance.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Trial balance', href: '/accounting/trial-balance' },
    ],
};
