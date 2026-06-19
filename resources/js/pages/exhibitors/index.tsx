import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import EventFormModal from './event-form-modal';
import type { BookingOption } from './event-form-modal';
import ExhibitorFormModal from './exhibitor-form-modal';

type Order = {
    id: number;
    order_number: string;
    status: string;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    last_payment_brand: string | null;
    last_payment_last4: string | null;
};

type ExhibitorRow = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    booth_assignment: string | null;
    booth_size: string | null;
    event: { id: number; name: string; portal_slug: string } | null;
    order: Order | null;
};

type EventOption = {
    id: number;
    name: string;
    portal_slug: string;
    exhibitor_count: number;
};

type Props = {
    exhibitors: {
        data: ExhibitorRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    events: EventOption[];
    statuses: string[];
    filters: { event_id: number | null; status: string | null };
    summary: Record<
        string,
        { count: number; total_cents: number; paid_cents: number }
    >;
    bookings: BookingOption[];
};

const STATUS_COLORS: Record<string, string> = {
    cart: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    pending:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    partially_paid:
        'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    paid: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    refunded:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

export default function ExhibitorsIndex({
    exhibitors,
    events,
    statuses,
    filters,
    summary,
    bookings,
}: Props) {
    const [creatingExhibitor, setCreatingExhibitor] = useState(false);
    const [creatingEvent, setCreatingEvent] = useState(false);

    const onFilter = (key: 'event_id' | 'status', value: string) => {
        const params: Record<string, string> = {};
        const updated = { ...filters, [key]: value || null };

        if (updated.event_id) {
            params.event_id = String(updated.event_id);
        }

        if (updated.status) {
            params.status = updated.status;
        }

        router.get('/exhibitors', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const totals = Object.values(summary).reduce(
        (acc, row) => ({
            total: acc.total + Number(row.total_cents),
            paid: acc.paid + Number(row.paid_cents),
        }),
        { total: 0, paid: 0 },
    );

    const columns = useMemo<ColumnDef<ExhibitorRow>[]>(
        () => [
            {
                accessorKey: 'booth_assignment',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Booth" />
                ),
                cell: ({ row }) => (
                    <div className="font-mono text-xs">
                        <div>{row.original.booth_assignment ?? '-'}</div>
                        <div className="text-[10px] text-muted-foreground">
                            {row.original.booth_size ?? ''}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'company_name',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Exhibitor" />
                ),
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">
                            {row.original.company_name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.contact_name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.email}
                        </div>
                    </div>
                ),
            },
            {
                id: 'event',
                accessorFn: (row) => row.event?.name ?? '',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Event" />
                ),
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground">
                        {row.original.event?.name ?? '-'}
                    </span>
                ),
            },
            {
                id: 'order',
                accessorFn: (row) => row.order?.order_number ?? '',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Order" />
                ),
                cell: ({ row }) => (
                    <span className="font-mono text-xs">
                        {row.original.order?.order_number ?? '-'}
                    </span>
                ),
            },
            {
                id: 'orderStatus',
                accessorFn: (row) => row.order?.status ?? '',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Status" />
                ),
                cell: ({ row }) =>
                    row.original.order ? (
                        <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[row.original.order.status] ?? ''}`}
                        >
                            {row.original.order.status.replace('_', ' ')}
                        </span>
                    ) : (
                        <span className="text-xs">-</span>
                    ),
            },
            {
                id: 'balance',
                accessorFn: (row) => row.order?.balance_cents ?? 0,
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title="Balance / Total"
                        className="ml-auto"
                    />
                ),
                cell: ({ row }) =>
                    row.original.order ? (
                        <div className="text-right font-mono text-xs">
                            <div>{money(row.original.order.balance_cents)}</div>
                            <div className="text-[10px] text-muted-foreground">
                                of {money(row.original.order.total_cents)}
                            </div>
                        </div>
                    ) : (
                        <span className="text-xs">-</span>
                    ),
            },
            {
                id: 'payment',
                header: 'Payment',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.order?.last_payment_brand ? (
                        <span className="font-mono text-[10px] uppercase">
                            {row.original.order.last_payment_brand} ····
                            {row.original.order.last_payment_last4}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground italic">
                            none
                        </span>
                    ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Exhibitors" />

            <ExhibitorFormModal
                open={creatingExhibitor}
                onClose={() => setCreatingExhibitor(false)}
                events={events}
                mode="create"
                defaultEventId={filters.event_id}
            />
            <EventFormModal
                open={creatingEvent}
                onClose={() => setCreatingEvent(false)}
                bookings={bookings}
                mode="create"
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Exhibitors
                            <HelpLink slug="exhibitors/overview" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {exhibitors.meta.total} exhibitors ·{' '}
                            {money(totals.paid)} collected of{' '}
                            {money(totals.total)} · payments via BluePay (fake
                            driver)
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <select
                            value={filters.event_id ?? ''}
                            onChange={(e) =>
                                onFilter('event_id', e.target.value)
                            }
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter by event"
                        >
                            <option value="">All events</option>
                            {events.map((ev) => (
                                <option key={ev.id} value={ev.id}>
                                    {ev.name} ({ev.exhibitor_count})
                                </option>
                            ))}
                        </select>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setCreatingEvent(true)}
                            disabled={bookings.length === 0}
                            data-tour-id="ex-new-event"
                        >
                            + New event
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => setCreatingExhibitor(true)}
                            disabled={events.length === 0}
                            data-tour-id="ex-new"
                        >
                            + New exhibitor
                        </Button>
                    </div>
                </header>

                {filters.status ? (
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            Filtered to{' '}
                            <span className="font-medium text-foreground">
                                {filters.status.replace('_', ' ')}
                            </span>
                        </span>
                        <button
                            type="button"
                            onClick={() => onFilter('status', '')}
                            className="rounded-full px-2 py-0.5 underline hover:text-foreground"
                        >
                            Clear filter
                        </button>
                    </div>
                ) : null}

                <div className="grid gap-2 sm:grid-cols-3 md:grid-cols-6">
                    {statuses.map((status) => {
                        const row = summary?.[status];

                        return (
                            <button
                                key={status}
                                type="button"
                                aria-pressed={filters.status === status}
                                onClick={() =>
                                    onFilter(
                                        'status',
                                        filters.status === status ? '' : status,
                                    )
                                }
                                className={`flex flex-col gap-1 rounded-lg border border-border p-2 text-left text-xs transition-colors hover:bg-muted ${filters.status === status ? 'bg-muted' : ''}`}
                            >
                                <span
                                    className={`inline-flex w-fit items-center rounded-full px-2 py-0.5 font-medium ${STATUS_COLORS[status] ?? ''}`}
                                >
                                    {status.replace('_', ' ')}
                                </span>
                                <span className="font-mono text-sm">
                                    {row?.count ?? 0}
                                </span>
                                {row?.total_cents ? (
                                    <span className="text-muted-foreground">
                                        {money(Number(row.total_cents))}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                <DataTable
                    columns={columns}
                    data={exhibitors.data}
                    emptyMessage="No exhibitors match."
                />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {exhibitors.meta.current_page} of{' '}
                        {exhibitors.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {exhibitors.links.prev ? (
                            <Link
                                href={exhibitors.links.prev}
                                preserveScroll
                                className="rounded border border-border px-2 py-1"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {exhibitors.links.next ? (
                            <Link
                                href={exhibitors.links.next}
                                preserveScroll
                                className="rounded border border-border px-2 py-1"
                            >
                                Next
                            </Link>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

ExhibitorsIndex.layout = {
    breadcrumbs: [{ title: 'Exhibitors', href: '/exhibitors' }],
};
