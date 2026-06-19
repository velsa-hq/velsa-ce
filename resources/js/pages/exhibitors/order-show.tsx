import { Head, Link, router } from '@inertiajs/react';
import { Check, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrderPayments from './order-payments';
import type { Payment } from './order-payments';

type CatalogItem = {
    id: number;
    sku: string;
    name: string;
    unit_label: string;
    unit_price_cents: number;
    category: {
        code: string;
        name: string;
        department: string | null;
    } | null;
};

type OrderItem = {
    id: number;
    sku: string;
    name: string;
    department: string | null;
    gl_account: string | null;
    quantity: number;
    unit_price_cents: number;
    line_total_cents: number;
    equipment_item_id: number | null;
};

type Order = {
    id: number;
    order_number: string;
    status: string | null;
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    placed_at: string | null;
    items: OrderItem[];
    payments: Payment[];
};

type Exhibitor = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
};

type Props = {
    exhibitor: Exhibitor;
    order: Order;
    catalog: CatalogItem[];
};

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    partially_paid: 'default',
    paid: 'outline',
    cancelled: 'destructive',
    refunded: 'destructive',
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function ExhibitorOrderShow({
    exhibitor,
    order,
    catalog,
}: Props) {
    const [search, setSearch] = useState('');
    const [picked, setPicked] = useState<number | null>(null);
    const [quantity, setQuantity] = useState(1);

    const filtered = useMemo(() => {
        const s = search.trim().toLowerCase();

        if (!s) {
            return catalog;
        }

        return catalog.filter(
            (c) =>
                c.sku.toLowerCase().includes(s) ||
                c.name.toLowerCase().includes(s) ||
                (c.category?.name ?? '').toLowerCase().includes(s),
        );
    }, [search, catalog]);

    const grouped = useMemo(() => {
        const groups: Record<string, CatalogItem[]> = {};

        for (const c of filtered) {
            const key = c.category?.name ?? 'Uncategorized';
            (groups[key] ??= []).push(c);
        }

        return Object.entries(groups).sort(([a], [b]) => a.localeCompare(b));
    }, [filtered]);

    const addItem = () => {
        if (picked === null) {
            return;
        }

        router.post(
            `/exhibitors/${exhibitor.id}/orders/${order.id}/items`,
            { equipment_item_id: picked, quantity },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPicked(null);
                    setQuantity(1);
                },
            },
        );
    };

    const removeItem = (itemId: number) => {
        if (!confirm('Remove this line item?')) {
            return;
        }

        router.delete(
            `/exhibitors/${exhibitor.id}/orders/${order.id}/items/${itemId}`,
            { preserveScroll: true },
        );
    };

    const blocksEdit = order.status === 'paid' || order.status === 'refunded';
    const base = `/exhibitors/${exhibitor.id}/orders/${order.id}`;

    const updateQty = (itemId: number, qty: number) => {
        router.patch(
            `${base}/items/${itemId}`,
            { quantity: qty },
            { preserveScroll: true },
        );
    };

    const setStatus = (status: 'pending' | 'cancelled') => {
        const verb = status === 'cancelled' ? 'Cancel' : 'Reopen';

        if (!confirm(`${verb} this order?`)) {
            return;
        }

        router.patch(`${base}/status`, { status }, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`${order.order_number} · Order`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Order{' '}
                            <span className="font-mono">
                                {order.order_number}
                            </span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={`/exhibitors/${exhibitor.id}`}
                                className="hover:underline"
                            >
                                {exhibitor.company_name}
                            </Link>
                            {' · '}
                            {exhibitor.contact_name} ({exhibitor.email})
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {order.status && (
                            <Badge
                                variant={
                                    STATUS_VARIANTS[order.status] ?? 'secondary'
                                }
                            >
                                {order.status.replace(/_/g, ' ')}
                            </Badge>
                        )}
                        {order.status === 'cancelled' ? (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setStatus('pending')}
                            >
                                Reopen
                            </Button>
                        ) : order.paid_cents === 0 ? (
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-rose-700 hover:text-rose-800 dark:text-rose-300"
                                onClick={() => setStatus('cancelled')}
                            >
                                Cancel order
                            </Button>
                        ) : null}
                    </div>
                </header>

                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    {/* Line items */}
                    <section className="rounded-xl border border-border bg-card">
                        <div className="border-b border-border px-4 py-3">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Line items ({order.items.length})
                            </h2>
                        </div>
                        <table className="w-full text-sm">
                            <thead className="bg-muted/30">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium">
                                        SKU
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        Item
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        Dept
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Qty
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Unit
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Line total
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {order.items.map((i) => (
                                    <tr
                                        key={i.id}
                                        className="border-t border-border/60"
                                    >
                                        <td className="px-4 py-2 font-mono text-xs">
                                            {i.sku}
                                        </td>
                                        <td className="px-4 py-2">{i.name}</td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {i.department ?? '-'}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            <QtyCell
                                                item={i}
                                                disabled={blocksEdit}
                                                onSave={(qty) =>
                                                    updateQty(i.id, qty)
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {money(i.unit_price_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right tabular-nums">
                                            {money(i.line_total_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => removeItem(i.id)}
                                                disabled={blocksEdit}
                                                aria-label="Remove item"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                                {order.items.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-6 text-center text-sm text-muted-foreground"
                                        >
                                            No items yet - add from the catalog
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                            <tfoot className="border-t-2 border-border bg-muted/40">
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-2 text-right text-muted-foreground"
                                    >
                                        Subtotal
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {money(order.subtotal_cents)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-2 text-right text-muted-foreground"
                                    >
                                        Tax
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {money(order.tax_cents)}
                                    </td>
                                    <td />
                                </tr>
                                <tr className="font-semibold">
                                    <td
                                        colSpan={5}
                                        className="px-4 py-2 text-right"
                                    >
                                        Total
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {money(order.total_cents)}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-2 text-right text-muted-foreground"
                                    >
                                        Paid
                                    </td>
                                    <td className="px-4 py-2 text-right text-emerald-700 tabular-nums dark:text-emerald-300">
                                        {money(order.paid_cents)}
                                    </td>
                                    <td />
                                </tr>
                                <tr className="font-semibold">
                                    <td
                                        colSpan={5}
                                        className="px-4 py-2 text-right"
                                    >
                                        Balance due
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {money(order.balance_cents)}
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    </section>

                    {/* Catalog picker + payments */}
                    <aside className="flex flex-col gap-4">
                        <section className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Add from catalog
                            </h2>
                            <div className="flex flex-col gap-3">
                                <Input
                                    type="search"
                                    placeholder="Search SKU or name..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                                <div className="max-h-[40vh] overflow-y-auto rounded-md border border-border">
                                    {grouped.map(([category, items]) => (
                                        <div key={category}>
                                            <div className="sticky top-0 bg-muted px-3 py-1 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                {category}
                                            </div>
                                            {items.map((c) => (
                                                <label
                                                    key={c.id}
                                                    className={`flex cursor-pointer items-start gap-2 border-t border-border/40 p-2 text-sm hover:bg-muted/30 ${
                                                        picked === c.id
                                                            ? 'bg-primary/10'
                                                            : ''
                                                    }`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="catalog-item"
                                                        checked={
                                                            picked === c.id
                                                        }
                                                        onChange={() =>
                                                            setPicked(c.id)
                                                        }
                                                        className="mt-1"
                                                    />
                                                    <div className="flex flex-1 flex-col">
                                                        <span>{c.name}</span>
                                                        <span className="text-xs text-muted-foreground">
                                                            <span className="font-mono">
                                                                {c.sku}
                                                            </span>{' '}
                                                            ·{' '}
                                                            {money(
                                                                c.unit_price_cents,
                                                            )}{' '}
                                                            / {c.unit_label}
                                                        </span>
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                                <div className="flex items-end gap-2">
                                    <div className="flex flex-col gap-1">
                                        <Label htmlFor="quantity">
                                            Quantity
                                        </Label>
                                        <Input
                                            id="quantity"
                                            type="number"
                                            min={1}
                                            max={9999}
                                            value={quantity}
                                            onChange={(e) =>
                                                setQuantity(
                                                    Math.max(
                                                        1,
                                                        Number(e.target.value),
                                                    ),
                                                )
                                            }
                                            className="w-24"
                                        />
                                    </div>
                                    <Button
                                        onClick={addItem}
                                        disabled={picked === null || blocksEdit}
                                    >
                                        Add to order
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <OrderPayments
                            exhibitorId={exhibitor.id}
                            orderId={order.id}
                            balanceCents={order.balance_cents}
                            payments={order.payments}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

function QtyCell({
    item,
    disabled,
    onSave,
}: {
    item: OrderItem;
    disabled: boolean;
    onSave: (qty: number) => void;
}) {
    const [value, setValue] = useState(String(item.quantity));
    const dirty = Number(value) !== item.quantity && Number(value) >= 1;

    if (disabled) {
        return <span>{item.quantity}</span>;
    }

    return (
        <span className="inline-flex items-center justify-end gap-1">
            <Input
                type="number"
                min={1}
                max={9999}
                value={value}
                onChange={(e) => setValue(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && dirty) {
                        onSave(Number(value));
                    }
                }}
                className="h-8 w-16 text-right"
                aria-label={`Quantity for ${item.name}`}
            />
            {dirty ? (
                <Button
                    size="sm"
                    variant="ghost"
                    className="h-8 px-2"
                    onClick={() => onSave(Number(value))}
                    aria-label="Save quantity"
                >
                    <Check className="size-4" />
                </Button>
            ) : null}
        </span>
    );
}

ExhibitorOrderShow.layout = {
    breadcrumbs: [
        { title: 'Exhibitors', href: '/exhibitors' },
        { title: 'Order', href: '#' },
    ],
};
