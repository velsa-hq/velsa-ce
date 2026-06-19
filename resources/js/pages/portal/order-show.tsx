import { Head, Link, router } from '@inertiajs/react';
import { ShoppingBag, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Item = {
    id: number;
    sku: string;
    name: string;
    quantity: number;
    unit_price_cents: number;
    line_total_cents: number;
};

type Payment = {
    id: number;
    amount_cents: number;
    card_brand: string | null;
    last4: string | null;
    captured_at: string | null;
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
    is_editable: boolean;
    items: Item[];
    payments: Payment[];
};

type Props = { order: Order };

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

export default function PortalOrderShow({ order }: Props) {
    const removeItem = (itemId: number) => {
        if (!confirm('Remove this item from the order?')) {
            return;
        }

        router.delete(`/portal/orders/${order.id}/items/${itemId}`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title={`Order ${order.order_number}`} />

            <div className="flex flex-col gap-6">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Order{' '}
                            <span className="font-mono">
                                {order.order_number}
                            </span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {order.items.length} item
                            {order.items.length === 1 ? '' : 's'}
                            {order.placed_at && (
                                <>
                                    {' · '}placed{' '}
                                    {new Date(
                                        order.placed_at,
                                    ).toLocaleDateString()}
                                </>
                            )}
                        </p>
                    </div>
                    {order.status && (
                        <Badge
                            variant={
                                STATUS_VARIANTS[order.status] ?? 'secondary'
                            }
                        >
                            {order.status.replace(/_/g, ' ')}
                        </Badge>
                    )}
                </header>

                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    {/* Items */}
                    <section className="rounded-xl border border-border bg-card">
                        <div className="border-b border-border px-4 py-3">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Line items
                            </h2>
                        </div>
                        {order.items.length === 0 ? (
                            <div className="p-6 text-center text-sm text-muted-foreground">
                                Order is empty.{' '}
                                <Link
                                    href="/portal/catalog"
                                    className="text-primary hover:underline"
                                >
                                    Browse catalog
                                </Link>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="bg-muted/30">
                                    <tr>
                                        <th className="px-4 py-2 text-left font-medium">
                                            Item
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Qty
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Unit
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Total
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
                                            <td className="px-4 py-2">
                                                <div className="flex flex-col">
                                                    <span>{i.name}</span>
                                                    <span className="font-mono text-xs text-muted-foreground">
                                                        {i.sku}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {i.quantity}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {money(i.unit_price_cents)}
                                            </td>
                                            <td className="px-4 py-2 text-right tabular-nums">
                                                {money(i.line_total_cents)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                {order.is_editable && (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() =>
                                                            removeItem(i.id)
                                                        }
                                                        aria-label={`Remove ${i.name}`}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </section>

                    {/* Totals + payments */}
                    <aside className="flex flex-col gap-4">
                        <section className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Totals
                            </h2>
                            <dl className="grid gap-1 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Subtotal
                                    </dt>
                                    <dd className="tabular-nums">
                                        {money(order.subtotal_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Tax
                                    </dt>
                                    <dd className="tabular-nums">
                                        {money(order.tax_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                    <dt>Total</dt>
                                    <dd className="tabular-nums">
                                        {money(order.total_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Paid
                                    </dt>
                                    <dd className="text-emerald-700 tabular-nums dark:text-emerald-300">
                                        {money(order.paid_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                    <dt>Balance due</dt>
                                    <dd className="tabular-nums">
                                        {money(order.balance_cents)}
                                    </dd>
                                </div>
                            </dl>

                            {order.balance_cents > 0 && (
                                <Button asChild className="mt-4 w-full">
                                    <Link
                                        href={`/portal/orders/${order.id}/pay`}
                                    >
                                        Pay {money(order.balance_cents)}
                                    </Link>
                                </Button>
                            )}

                            <Button
                                asChild
                                variant="outline"
                                className="mt-2 w-full"
                            >
                                <Link
                                    href={`/portal/orders/${order.id}/invoice`}
                                >
                                    View / print invoice
                                </Link>
                            </Button>

                            {order.is_editable && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-2 w-full"
                                >
                                    <Link href="/portal/catalog">
                                        <ShoppingBag className="size-4" />
                                        Add more items
                                    </Link>
                                </Button>
                            )}
                        </section>

                        <section className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Payments
                            </h2>
                            {order.payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No payments yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {order.payments.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex justify-between border-b border-border/40 pb-2 last:border-0"
                                        >
                                            <span>
                                                {p.card_brand ?? 'card'}{' '}
                                                {p.last4 ? `••${p.last4}` : ''}
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {p.captured_at
                                                        ? new Date(
                                                              p.captured_at,
                                                          ).toLocaleDateString()
                                                        : ''}
                                                </span>
                                            </span>
                                            <span className="tabular-nums">
                                                {money(p.amount_cents)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}
