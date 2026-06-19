import { Head, Link, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Invoice = {
    id: number;
    number: string;
    status: string | null;
    status_label: string | null;
    dunning_stage: string | null;
    dunning_label: string | null;
    source: string;
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    issued_on: string | null;
    due_on: string | null;
    days_past_due: number;
    is_past_due: boolean;
};

type Props = {
    invoices: {
        data: Invoice[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string | null; stage: string | null };
    statuses: { value: string; label: string }[];
    summary: {
        total_outstanding_cents: number;
        past_due_cents: number;
        open_count: number;
        past_due_count: number;
    };
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    issued: 'default',
    partial_paid: 'default',
    paid: 'outline',
    past_due: 'destructive',
    void: 'secondary',
    written_off: 'destructive',
};

const STAGE_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    none: 'outline',
    first_notice: 'secondary',
    second_notice: 'default',
    final_notice: 'destructive',
    collections: 'destructive',
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function InvoicesIndex({
    invoices,
    filters,
    statuses,
    summary,
}: Props) {
    const setFilter = (key: string, value: string | null) => {
        router.get(
            '/admin/invoices',
            { ...filters, [key]: value || null },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <>
            <Head title="Invoices · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header>
                    <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        Invoices
                        <HelpLink slug="accounting/invoicing" />
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Accounts receivable - invoices, statements, and dunning.
                    </p>
                </header>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Outstanding
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {money(summary.total_outstanding_cents)}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Past due
                        </div>
                        <div className="mt-1 text-2xl font-semibold text-amber-700 tabular-nums dark:text-amber-300">
                            {money(summary.past_due_cents)}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Open invoices
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {summary.open_count}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Past due count
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {summary.past_due_count}
                        </div>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Status:</span>
                    <Button
                        type="button"
                        variant={!filters.status ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setFilter('status', null)}
                        className="rounded-full"
                    >
                        All
                    </Button>
                    {statuses.map((s) => (
                        <Button
                            key={s.value}
                            type="button"
                            variant={
                                filters.status === s.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() => setFilter('status', s.value)}
                            className="rounded-full"
                        >
                            {s.label}
                        </Button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Invoice #
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Source
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Dunning
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Balance
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Due
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.map((inv) => (
                                <tr
                                    key={inv.id}
                                    className="border-t border-border/60 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 font-mono">
                                        <Link
                                            href={`/admin/invoices/${inv.number}`}
                                            data-tour-id="inv-row"
                                            className="hover:underline"
                                        >
                                            {inv.number}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{inv.source}</td>
                                    <td className="px-4 py-3">
                                        {inv.status && (
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[
                                                        inv.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {inv.status_label}
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {inv.dunning_stage &&
                                            inv.dunning_stage !== 'none' && (
                                                <Badge
                                                    variant={
                                                        STAGE_VARIANTS[
                                                            inv.dunning_stage
                                                        ] ?? 'secondary'
                                                    }
                                                >
                                                    {inv.dunning_label}
                                                </Badge>
                                            )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {money(inv.total_cents)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {inv.balance_cents > 0 ? (
                                            <span
                                                className={
                                                    inv.is_past_due
                                                        ? 'text-amber-700 dark:text-amber-300'
                                                        : ''
                                                }
                                            >
                                                {money(inv.balance_cents)}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                paid
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {inv.due_on ?? '-'}
                                        {inv.is_past_due && (
                                            <span className="ml-1 text-xs text-amber-700 dark:text-amber-300">
                                                ({inv.days_past_due}d)
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {invoices.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No invoices match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {invoices.meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {invoices.meta.current_page} of{' '}
                            {invoices.meta.last_page}
                        </span>
                        <div className="flex gap-2">
                            {invoices.links.prev && (
                                <Link
                                    href={invoices.links.prev}
                                    className="text-foreground hover:underline"
                                >
                                    Prev
                                </Link>
                            )}
                            {invoices.links.next && (
                                <Link
                                    href={invoices.links.next}
                                    className="text-foreground hover:underline"
                                >
                                    Next
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/invoices' },
        { title: 'Invoices', href: '/admin/invoices' },
    ],
};
