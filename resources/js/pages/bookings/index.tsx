import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { wallClock } from '@/lib/datetime';
import {
    create as bookingsCreate,
    show as bookingShow,
} from '@/routes/bookings';

type Space = { name: string | null; kind: string | null };
type BookingRow = {
    id: number;
    reference: string;
    name: string;
    kind: string | null;
    status: string;
    start_at: string | null;
    end_at: string | null;
    total_cents: number;
    attendance_estimate: number | null;
    venue: { id: number; name: string; slug: string } | null;
    client_name: string | null;
    owner_email: string | null;
    spaces: Space[];
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    bookings: {
        data: BookingRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    venues: VenueOption[];
    statuses: string[];
    filters: {
        venue_id: number | null;
        status: string | null;
        from: string | null;
        to: string | null;
    };
    summary: {
        total: number;
        by_status: Record<string, { count: number; total_cents: number }>;
    };
};

const STATUS_COLORS: Record<string, string> = {
    inquiry:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
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

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function BookingsIndex({
    bookings,
    venues,
    statuses,
    filters,
    summary,
}: Props) {
    const onFilter = (
        key: 'venue_id' | 'status' | 'from' | 'to',
        value: string,
    ) => {
        const params: Record<string, string> = {};
        const newFilters = { ...filters, [key]: value || null };

        if (newFilters.venue_id) {
            params.venue_id = String(newFilters.venue_id);
        }

        if (newFilters.status) {
            params.status = newFilters.status;
        }

        if (newFilters.from) {
            params.from = newFilters.from;
        }

        if (newFilters.to) {
            params.to = newFilters.to;
        }

        router.get('/bookings', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns = useMemo<ColumnDef<BookingRow>[]>(
        () => [
            {
                id: 'booking',
                accessorKey: 'name',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Booking" />
                ),
                cell: ({ row }) => (
                    <Link
                        href={bookingShow(row.original.id).url}
                        className="flex flex-col hover:underline"
                    >
                        <span className="leading-tight font-medium">
                            {row.original.name}
                        </span>
                        <span className="font-mono text-xs text-muted-foreground">
                            {row.original.reference}
                        </span>
                    </Link>
                ),
            },
            {
                accessorKey: 'status',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Status" />
                ),
                cell: ({ row }) => (
                    <span
                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[row.original.status] ?? ''}`}
                    >
                        {row.original.status}
                    </span>
                ),
            },
            {
                id: 'when',
                accessorKey: 'start_at',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="When" />
                ),
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div>{formatDateTime(row.original.start_at)}</div>
                        <div className="text-muted-foreground">
                            to {formatDateTime(row.original.end_at)}
                        </div>
                    </div>
                ),
            },
            {
                id: 'venue',
                accessorFn: (row) => row.venue?.name ?? '',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title="Venue · Spaces"
                    />
                ),
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div className="font-medium">
                            {row.original.venue?.name ?? '-'}
                        </div>
                        <div className="text-muted-foreground">
                            {row.original.spaces
                                .map((s) => s.name)
                                .filter(Boolean)
                                .join(', ') || '-'}
                        </div>
                    </div>
                ),
            },
            {
                accessorKey: 'client_name',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Client" />
                ),
                cell: ({ row }) => (
                    <span className="text-xs">
                        {row.original.client_name ?? '-'}
                    </span>
                ),
            },
            {
                accessorKey: 'total_cents',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title="Total"
                        className="ml-auto"
                    />
                ),
                cell: ({ row }) => (
                    <div className="text-right font-mono text-xs">
                        <div>{money(row.original.total_cents)}</div>
                        <Link
                            href={`/bookings/${row.original.id}/diagram`}
                            className="mt-1 inline-block text-[10px] font-medium text-blue-700 hover:underline dark:text-blue-300"
                        >
                            Plan
                        </Link>
                    </div>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Bookings" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Bookings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {summary.total} bookings
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild>
                            <Link href={bookingsCreate().url}>
                                + New booking
                            </Link>
                        </Button>
                        <select
                            value={filters.venue_id ?? ''}
                            onChange={(e) =>
                                onFilter('venue_id', e.target.value)
                            }
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter by venue"
                        >
                            <option value="">All venues</option>
                            {venues.map((v) => (
                                <option key={v.id} value={v.id}>
                                    {v.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.status ?? ''}
                            onChange={(e) => onFilter('status', e.target.value)}
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter by status"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((s) => (
                                <option key={s} value={s}>
                                    {s}
                                </option>
                            ))}
                        </select>
                        <input
                            type="date"
                            value={filters.from ?? ''}
                            onChange={(e) => onFilter('from', e.target.value)}
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter from date"
                        />
                        <input
                            type="date"
                            value={filters.to ?? ''}
                            onChange={(e) => onFilter('to', e.target.value)}
                            className="rounded-md border border-border bg-background px-2 py-1 text-sm"
                            aria-label="Filter to date"
                        />
                    </div>
                </header>

                <div className="grid gap-2 sm:grid-cols-3 md:grid-cols-6">
                    {statuses.map((status) => {
                        const row = summary.by_status?.[status];

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
                                    {status}
                                </span>
                                <span className="font-mono text-sm text-foreground">
                                    {row?.count ?? 0}
                                </span>
                                {row?.total_cents ? (
                                    <span className="text-muted-foreground">
                                        {money(row.total_cents)}
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                <DataTable
                    columns={columns}
                    data={bookings.data}
                    emptyMessage="No bookings match."
                />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {bookings.meta.current_page} of{' '}
                        {bookings.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {bookings.links.prev ? (
                            <Link
                                href={bookings.links.prev}
                                preserveScroll
                                className="rounded border border-border px-2 py-1"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {bookings.links.next ? (
                            <Link
                                href={bookings.links.next}
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

BookingsIndex.layout = {
    breadcrumbs: [{ title: 'Bookings', href: '/bookings' }],
};
