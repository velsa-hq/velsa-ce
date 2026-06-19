import { Head, Link, router } from '@inertiajs/react';
import { show as workOrderShow } from '@/routes/work-orders';

type Row = {
    id: number;
    applied_at: string | null;
    resource_name: string | null;
    resource_sku: string | null;
    action: string;
    quantity: number | null;
    unit: string | null;
    work_order_id: number;
    work_order_reference: string | null;
    work_order_title: string | null;
};

type Venue = { id: number; name: string };

type Props = {
    rows: Row[];
    venues: Venue[];
    filters: { venue_id: number | null };
};

const ACTION_TONE: Record<string, string> = {
    Deploy: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    Return: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    Consume: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    Replace:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
};

export default function InventoryActivity({ rows, venues, filters }: Props) {
    const onVenueFilter = (value: string) =>
        router.get('/inventory/activity', value ? { venue_id: value } : {}, {
            preserveState: true,
            preserveScroll: true,
        });

    return (
        <>
            <Head title="Inventory activity" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <Link
                            href="/inventory"
                            className="text-xs text-muted-foreground hover:underline"
                        >
                            Inventory
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Inventory activity
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Stock movements applied by completed work orders ·
                            latest {rows.length}
                        </p>
                    </div>
                    <select
                        value={filters.venue_id ?? ''}
                        onChange={(e) => onVenueFilter(e.target.value)}
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

                <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    When
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Resource
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Action
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Qty
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Work order
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No applied movements yet - complete a
                                        work order with inventory-linked items.
                                    </td>
                                </tr>
                            ) : (
                                rows.map((r, idx) => (
                                    <tr
                                        key={r.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2 text-xs whitespace-nowrap">
                                            {r.applied_at ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div>{r.resource_name ?? '-'}</div>
                                            {r.resource_sku ? (
                                                <div className="font-mono text-xs text-muted-foreground">
                                                    {r.resource_sku}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_TONE[r.action] ?? ''}`}
                                            >
                                                {r.action}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {r.quantity ?? '-'}
                                            {r.unit ? ` ${r.unit}` : ''}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            <Link
                                                href={
                                                    workOrderShow(
                                                        r.work_order_id,
                                                    ).url
                                                }
                                                className="hover:underline"
                                            >
                                                <span className="font-mono">
                                                    {r.work_order_reference}
                                                </span>
                                                {r.work_order_title
                                                    ? ` · ${r.work_order_title}`
                                                    : ''}
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

InventoryActivity.layout = {
    breadcrumbs: [
        { title: 'Inventory', href: '/inventory' },
        { title: 'Activity', href: '/inventory/activity' },
    ],
};
