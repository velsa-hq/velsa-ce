import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DataTableColumnHeader } from '@/components/ui/data-table-column-header';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { wallClock } from '@/lib/datetime';
import { show as workOrderShow } from '@/routes/work-orders';

type Option = { value: string; label: string };
type Assignee = { id: number; name: string; email: string };

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

const PRIORITIES: { value: number; label: string }[] = [
    { value: 1, label: 'P1 · Critical' },
    { value: 2, label: 'P2 · High' },
    { value: 3, label: 'P3 · Normal' },
    { value: 4, label: 'P4 · Low' },
    { value: 5, label: 'P5 · Backlog' },
];

type WorkOrderRow = {
    id: number;
    reference: string;
    title: string;
    kind: string;
    status: string;
    priority: number;
    scheduled_for: string | null;
    completed_at: string | null;
    cost_cents: number;
    venue_name: string | null;
    assignee_email: string | null;
    template_name: string | null;
    item_count: number;
    is_overdue: boolean;
    is_recurring: boolean;
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    work_orders: {
        data: WorkOrderRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    venues: VenueOption[];
    statuses: string[];
    kinds: Option[];
    assignees: Assignee[];
    filters: { venue_id: number | null; status: string | null };
    summary: Record<string, { count: number; cost_cents: number }>;
    summary_window_days: number;
    overdue_count: number;
};

const TERMINAL_STATUSES = ['completed', 'cancelled'];

const STATUS_COLORS: Record<string, string> = {
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    open: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    assigned: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    in_progress:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    completed:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

const PRIORITY_LABEL: Record<number, string> = {
    1: 'Critical',
    2: 'High',
    3: 'Normal',
    4: 'Low',
    5: 'Backlog',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function fmtDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function WorkOrdersIndex({
    work_orders,
    venues,
    statuses,
    kinds,
    assignees,
    filters,
    summary,
    summary_window_days,
    overdue_count,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [creating, setCreating] = useState(false);

    const toggleId = (id: number, on: boolean) =>
        setSelectedIds((prev) => {
            const next = new Set(prev);

            if (on) {
                next.add(id);
            } else {
                next.delete(id);
            }

            return next;
        });

    const pageIds = work_orders.data.map((r) => r.id);
    const allSelected =
        pageIds.length > 0 && pageIds.every((id) => selectedIds.has(id));

    const filterQuery = new URLSearchParams({
        ...(filters.venue_id ? { venue_id: String(filters.venue_id) } : {}),
        ...(filters.status ? { status: filters.status } : {}),
    }).toString();
    const printHref =
        selectedIds.size > 0
            ? `/work-orders/print?ids=${[...selectedIds].join(',')}`
            : `/work-orders/print${filterQuery ? `?${filterQuery}` : ''}`;

    const onFilter = (key: 'venue_id' | 'status', value: string) => {
        const params: Record<string, string> = {};
        const updated = { ...filters, [key]: value || null };

        if (updated.venue_id) {
            params.venue_id = String(updated.venue_id);
        }

        if (updated.status) {
            params.status = updated.status;
        }

        router.get('/work-orders', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const columns = useMemo<ColumnDef<WorkOrderRow>[]>(
        () => [
            {
                id: 'select',
                enableHiding: false,
                header: () => (
                    <input
                        type="checkbox"
                        checked={allSelected}
                        onChange={(e) =>
                            setSelectedIds((prev) => {
                                const next = new Set(prev);
                                pageIds.forEach((id) =>
                                    e.target.checked
                                        ? next.add(id)
                                        : next.delete(id),
                                );

                                return next;
                            })
                        }
                        className="size-4 rounded border-border accent-primary"
                        aria-label="Select all"
                    />
                ),
                cell: ({ row }) => (
                    <input
                        type="checkbox"
                        checked={selectedIds.has(row.original.id)}
                        onChange={(e) =>
                            toggleId(row.original.id, e.target.checked)
                        }
                        className="size-4 rounded border-border accent-primary"
                        aria-label={`Select ${row.original.reference}`}
                    />
                ),
            },
            {
                accessorKey: 'reference',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Reference" />
                ),
                cell: ({ row }) => (
                    <Link
                        href={workOrderShow(row.original.id).url}
                        className="block font-mono text-xs hover:underline"
                    >
                        <div>{row.original.reference}</div>
                        {row.original.is_recurring ? (
                            <div className="text-[10px] text-muted-foreground">
                                ↻ {row.original.template_name}
                            </div>
                        ) : null}
                    </Link>
                ),
            },
            {
                accessorKey: 'title',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Title" />
                ),
                cell: ({ row }) => (
                    <Link
                        href={workOrderShow(row.original.id).url}
                        className="block hover:underline"
                    >
                        <div className="font-medium">{row.original.title}</div>
                        <div className="text-xs text-muted-foreground">
                            {row.original.kind?.replace('_', ' ')}
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
                    <div>
                        <span
                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[row.original.status] ?? ''}`}
                        >
                            {row.original.status.replace('_', ' ')}
                        </span>
                        {row.original.is_overdue ? (
                            <div className="mt-0.5 text-[10px] font-medium text-rose-700 dark:text-rose-300">
                                OVERDUE
                            </div>
                        ) : null}
                    </div>
                ),
            },
            {
                accessorKey: 'priority',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Priority" />
                ),
                cell: ({ row }) => (
                    <span className="text-xs">
                        P{row.original.priority} ·{' '}
                        {PRIORITY_LABEL[row.original.priority] ?? '-'}
                    </span>
                ),
            },
            {
                accessorKey: 'scheduled_for',
                header: ({ column }) => (
                    <DataTableColumnHeader column={column} title="Scheduled" />
                ),
                cell: ({ row }) => (
                    <span className="text-xs">
                        {fmtDateTime(row.original.scheduled_for)}
                    </span>
                ),
            },
            {
                id: 'venue',
                accessorKey: 'venue_name',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title="Venue · Assignee"
                    />
                ),
                cell: ({ row }) => (
                    <div className="text-xs">
                        <div>{row.original.venue_name ?? '-'}</div>
                        <div className="text-muted-foreground">
                            {row.original.assignee_email ?? 'unassigned'}
                        </div>
                    </div>
                ),
            },
            {
                id: 'cost',
                accessorKey: 'cost_cents',
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title="Items / Cost"
                        className="ml-auto"
                    />
                ),
                cell: ({ row }) => (
                    <div className="text-right font-mono text-xs">
                        <div>
                            {row.original.item_count} item
                            {row.original.item_count === 1 ? '' : 's'}
                        </div>
                        <div className="text-muted-foreground">
                            {money(row.original.cost_cents)}
                        </div>
                    </div>
                ),
            },
        ],

        [selectedIds, allSelected, pageIds],
    );

    return (
        <>
            <Head title="Work orders" />

            <WorkOrderCreateModal
                open={creating}
                venues={venues}
                kinds={kinds}
                assignees={assignees}
                defaultVenueId={filters.venue_id}
                onClose={() => setCreating(false)}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Work orders
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {work_orders.meta.total} work orders ·{' '}
                            {overdue_count > 0 ? (
                                <span className="font-medium text-rose-700 dark:text-rose-300">
                                    {overdue_count} overdue
                                </span>
                            ) : (
                                <span>on track</span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
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
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={printHref}
                                target="_blank"
                                rel="noopener"
                                data-tour-id="wo-print-group"
                            >
                                {selectedIds.size > 0
                                    ? `Print (${selectedIds.size})`
                                    : `Print all (${work_orders.meta.total})`}
                            </a>
                        </Button>
                        <Button
                            size="sm"
                            onClick={() => setCreating(true)}
                            data-tour-id="wo-new"
                        >
                            + New work order
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
                                {row?.cost_cents ? (
                                    <span className="text-muted-foreground">
                                        {money(row.cost_cents)}
                                    </span>
                                ) : null}
                                {TERMINAL_STATUSES.includes(status) ? (
                                    <span className="text-[10px] text-muted-foreground">
                                        last {summary_window_days}d
                                    </span>
                                ) : null}
                            </button>
                        );
                    })}
                </div>

                <DataTable
                    columns={columns}
                    data={work_orders.data}
                    emptyMessage="No work orders match."
                />

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {work_orders.meta.current_page} of{' '}
                        {work_orders.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {work_orders.links.prev ? (
                            <Link
                                href={work_orders.links.prev}
                                preserveScroll
                                className="rounded border border-border px-2 py-1"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {work_orders.links.next ? (
                            <Link
                                href={work_orders.links.next}
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

function WorkOrderCreateModal({
    open,
    venues,
    kinds,
    assignees,
    defaultVenueId,
    onClose,
}: {
    open: boolean;
    venues: VenueOption[];
    kinds: Option[];
    assignees: Assignee[];
    defaultVenueId: number | null;
    onClose: () => void;
}) {
    const [form, setForm] = useState({
        venue_id: String(defaultVenueId ?? venues[0]?.id ?? ''),
        title: '',
        kind: kinds[0]?.value ?? '',
        priority: 3,
        scheduled_for: '',
        assigned_to_user_id: '',
        description: '',
    });
    const [saving, setSaving] = useState(false);

    const set = <K extends keyof typeof form>(
        key: K,
        value: (typeof form)[K],
    ) => setForm((f) => ({ ...f, [key]: value }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        router.post(
            '/work-orders',
            {
                venue_id: Number(form.venue_id),
                title: form.title,
                kind: form.kind,
                priority: Number(form.priority),
                scheduled_for: form.scheduled_for || null,
                assigned_to_user_id: form.assigned_to_user_id
                    ? Number(form.assigned_to_user_id)
                    : null,
                description: form.description || null,
            },
            { onFinish: () => setSaving(false) },
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-2xl">
                {open ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>New work order</DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="wc-title">Title</Label>
                                <Input
                                    id="wc-title"
                                    value={form.title}
                                    onChange={(e) =>
                                        set('title', e.target.value)
                                    }
                                    placeholder="e.g. Replace HVAC filter in Hall A"
                                    required
                                    data-tour-id="wo-title"
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="wc-venue">Venue</Label>
                                <select
                                    id="wc-venue"
                                    value={form.venue_id}
                                    onChange={(e) =>
                                        set('venue_id', e.target.value)
                                    }
                                    className={selectClass}
                                    required
                                    data-tour-id="wo-venue"
                                >
                                    {venues.map((v) => (
                                        <option key={v.id} value={v.id}>
                                            {v.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="wc-kind">Kind</Label>
                                <select
                                    id="wc-kind"
                                    value={form.kind}
                                    onChange={(e) =>
                                        set('kind', e.target.value)
                                    }
                                    className={selectClass}
                                    data-tour-id="wo-kind"
                                >
                                    {kinds.map((k) => (
                                        <option key={k.value} value={k.value}>
                                            {k.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="wc-priority">Priority</Label>
                                <select
                                    id="wc-priority"
                                    value={form.priority}
                                    onChange={(e) =>
                                        set('priority', Number(e.target.value))
                                    }
                                    className={selectClass}
                                >
                                    {PRIORITIES.map((p) => (
                                        <option key={p.value} value={p.value}>
                                            {p.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="wc-when">Scheduled for</Label>
                                <Input
                                    id="wc-when"
                                    type="datetime-local"
                                    value={form.scheduled_for}
                                    onChange={(e) =>
                                        set('scheduled_for', e.target.value)
                                    }
                                />
                            </div>
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="wc-assignee">
                                    Assignee (optional)
                                </Label>
                                <select
                                    id="wc-assignee"
                                    value={form.assigned_to_user_id}
                                    onChange={(e) =>
                                        set(
                                            'assigned_to_user_id',
                                            e.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="">- Unassigned -</option>
                                    {assignees.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1.5 sm:col-span-2">
                                <Label htmlFor="wc-desc">
                                    Description (optional)
                                </Label>
                                <textarea
                                    id="wc-desc"
                                    rows={3}
                                    value={form.description}
                                    onChange={(e) =>
                                        set('description', e.target.value)
                                    }
                                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={saving}
                                data-tour-id="wo-submit"
                            >
                                Create work order
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

WorkOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Work orders', href: '/work-orders' }],
};
