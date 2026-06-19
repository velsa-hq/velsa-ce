import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import { archive, show as contractShow } from '@/routes/contracts';

type Signer = { name: string; email: string; viewed: boolean; signed: boolean };
type ContractRow = {
    id: number;
    reference: string;
    status: string;
    kind: string;
    total_cents: number;
    sent_at: string | null;
    viewed_at: string | null;
    signed_at: string | null;
    provider: string;
    provider_envelope_id: string | null;
    booking: {
        id: number;
        reference: string;
        name: string;
        start_at: string | null;
        venue_name: string | null;
        client_name: string | null;
    } | null;
    signers: Signer[];
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    contracts: {
        data: ContractRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    venues: VenueOption[];
    statuses: string[];
    filters: { status: string | null; venue_id: number | null };
    summary: Record<string, { count: number; total_cents: number }>;
};

const STATUS_COLORS: Record<string, string> = {
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    sent: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    viewed: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    partially_signed:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    signed: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    declined:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    expired:
        'bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100',
    voided: 'bg-neutral-300 text-neutral-700 dark:bg-neutral-600 dark:text-neutral-200',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function fmtDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleDateString();
}

export default function ContractsIndex({
    contracts,
    venues,
    statuses,
    filters,
    summary,
}: Props) {
    const [sendingId, setSendingId] = useState<number | null>(null);

    const onFilter = (key: 'venue_id' | 'status', value: string) => {
        const params: Record<string, string> = {};
        const updated = { ...filters, [key]: value || null };

        if (updated.venue_id) {
            params.venue_id = String(updated.venue_id);
        }

        if (updated.status) {
            params.status = updated.status;
        }

        router.get('/contracts', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const sendContract = (contract: ContractRow) => {
        if (!contract.booking?.client_name) {
            alert('Booking has no client to send to.');

            return;
        }

        const name = contract.booking.client_name;
        const email = window.prompt(
            `Send to which email for ${name}?`,
            'client@example.test',
        );

        if (!email) {
            return;
        }

        setSendingId(contract.id);
        router.post(
            `/contracts/${contract.id}/send`,
            { signers: [{ name, email, role: 'client' }] },
            { preserveScroll: true, onFinish: () => setSendingId(null) },
        );
    };

    const columns = useMemo<ColumnDef<ContractRow>[]>(
        () => [
            {
                accessorKey: 'reference',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Contract" />
                ),
                cell: ({ row }) => (
                    <Link
                        href={contractShow(row.original.id).url}
                        className="block font-mono text-xs hover:underline"
                    >
                        <div>{row.original.reference}</div>
                        <div className="text-[10px] text-muted-foreground">
                            {row.original.kind}
                        </div>
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
                        {row.original.status.replace('_', ' ')}
                    </span>
                ),
            },
            {
                id: 'booking',
                accessorFn: (row) => row.booking?.name ?? '',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Booking" />
                ),
                cell: ({ row }) =>
                    row.original.booking ? (
                        <div className="text-xs">
                            <div className="font-medium">
                                {row.original.booking.name}
                            </div>
                            <div className="text-muted-foreground">
                                {row.original.booking.venue_name} ·{' '}
                                {row.original.booking.client_name}
                            </div>
                            <div className="text-[10px] text-muted-foreground">
                                {row.original.booking.reference}
                            </div>
                        </div>
                    ) : (
                        <span className="text-xs text-muted-foreground">-</span>
                    ),
            },
            {
                id: 'signers',
                header: 'Signers',
                enableSorting: false,
                cell: ({ row }) =>
                    row.original.signers.length === 0 ? (
                        <span className="text-xs text-muted-foreground italic">
                            none yet
                        </span>
                    ) : (
                        <ul className="space-y-0.5 text-xs">
                            {row.original.signers.map((s) => (
                                <li key={s.email}>
                                    {s.signed ? '✓' : s.viewed ? '👁' : '·'}{' '}
                                    {s.name}
                                </li>
                            ))}
                        </ul>
                    ),
            },
            {
                id: 'lifecycle',
                accessorKey: 'sent_at',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Life-cycle" />
                ),
                cell: ({ row }) => (
                    <div className="text-xs text-muted-foreground">
                        <div>sent: {fmtDate(row.original.sent_at)}</div>
                        <div>viewed: {fmtDate(row.original.viewed_at)}</div>
                        <div>signed: {fmtDate(row.original.signed_at)}</div>
                    </div>
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
                        {money(row.original.total_cents)}
                    </div>
                ),
            },
            {
                id: 'actions',
                header: 'Actions',
                enableSorting: false,
                enableHiding: false,
                cell: ({ row }) =>
                    row.original.status === 'draft' ? (
                        <Button
                            size="sm"
                            onClick={() => sendContract(row.original)}
                            disabled={sendingId === row.original.id}
                        >
                            {sendingId === row.original.id
                                ? 'Sending...'
                                : 'Send'}
                        </Button>
                    ) : (
                        <span className="font-mono text-[10px] text-muted-foreground">
                            {row.original.provider_envelope_id?.slice(0, 12) ??
                                ''}
                        </span>
                    ),
            },
        ],
        [sendingId],
    );

    return (
        <>
            <Head title="Contracts" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Contracts
                            <HelpLink slug="contracts/overview" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {contracts.meta.total} contracts · provider:
                            DocuSign (fake driver) ·{' '}
                            <Link
                                href={archive().url}
                                className="hover:text-foreground hover:underline"
                            >
                                Archive
                            </Link>
                        </p>
                    </div>
                    <select
                        value={filters.venue_id ?? ''}
                        onChange={(e) => onFilter('venue_id', e.target.value)}
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
                </header>

                <div className="grid gap-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-8">
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
                            </button>
                        );
                    })}
                </div>

                <DataTable
                    columns={columns}
                    data={contracts.data}
                    emptyMessage="No contracts match."
                />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {contracts.meta.current_page} of{' '}
                        {contracts.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {contracts.links.prev ? (
                            <Link
                                href={contracts.links.prev}
                                preserveScroll
                                className="rounded border border-border px-2 py-1"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {contracts.links.next ? (
                            <Link
                                href={contracts.links.next}
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

ContractsIndex.layout = {
    breadcrumbs: [{ title: 'Contracts', href: '/contracts' }],
};
