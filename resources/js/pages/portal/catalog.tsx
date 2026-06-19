import { Head, Link, router } from '@inertiajs/react';
import { Plus, ShoppingBag } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Item = {
    id: number;
    sku: string;
    name: string;
    description: string | null;
    unit_label: string;
    unit_price_cents: number;
    late_price_cents: number | null;
    effective_price_cents: number;
};

type Pricing = {
    advance_rate_deadline: string | null;
    late_order_surcharge_pct: number;
    late_rate_active: boolean;
};

type Category = {
    code: string;
    name: string;
    description: string | null;
    department: string | null;
    items: Item[];
};

type CurrentOrder = {
    id: number;
    order_number: string;
    item_count: number;
} | null;

type Props = {
    categories: Category[];
    pricing: Pricing;
    current_order: CurrentOrder;
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function ItemCard({ item }: { item: Item }) {
    const [quantity, setQuantity] = useState(1);
    const [adding, setAdding] = useState(false);

    const add = () => {
        setAdding(true);
        router.post(
            '/portal/orders/items',
            { equipment_item_id: item.id, quantity },
            {
                preserveScroll: true,
                onFinish: () => setAdding(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3">
            <div className="flex items-start justify-between gap-2">
                <div>
                    <div className="font-medium">{item.name}</div>
                    <div className="font-mono text-xs text-muted-foreground">
                        {item.sku}
                    </div>
                </div>
                <div className="text-right">
                    <div
                        data-tour-id="portal-item-advance-price"
                        className="font-semibold tabular-nums"
                    >
                        {money(item.effective_price_cents)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        per {item.unit_label}
                    </div>
                    {item.late_price_cents !== null &&
                        item.late_price_cents !== item.unit_price_cents && (
                            <div
                                data-tour-id="portal-item-standard-price"
                                className="text-[11px] text-muted-foreground"
                            >
                                advance {money(item.unit_price_cents)} ·
                                standard {money(item.late_price_cents)}
                            </div>
                        )}
                </div>
            </div>
            {item.description && (
                <p className="text-xs text-muted-foreground">
                    {item.description}
                </p>
            )}
            <div className="flex items-center gap-2 pt-1">
                <Input
                    type="number"
                    min={1}
                    max={9999}
                    value={quantity}
                    onChange={(e) =>
                        setQuantity(Math.max(1, Number(e.target.value)))
                    }
                    className="w-20"
                    aria-label={`Quantity of ${item.name}`}
                />
                <Button
                    onClick={add}
                    disabled={adding}
                    size="sm"
                    data-tour-id="portal-add-item"
                    className="flex-1"
                >
                    <Plus className="size-4" />
                    Add to order
                </Button>
            </div>
        </div>
    );
}

function PricingBanner({ pricing }: { pricing: Pricing }) {
    if (
        !pricing.advance_rate_deadline ||
        pricing.late_order_surcharge_pct <= 0
    ) {
        return null;
    }

    const deadline = new Date(pricing.advance_rate_deadline).toLocaleDateString(
        undefined,
        {
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        },
    );

    if (pricing.late_rate_active) {
        return (
            <div
                data-tour-id="portal-pricing-banner"
                className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/30 dark:text-amber-100"
            >
                The advance-order deadline ({deadline}) has passed - orders now
                include a {pricing.late_order_surcharge_pct}% standard-rate
                surcharge.
            </div>
        );
    }

    return (
        <div
            data-tour-id="portal-pricing-banner"
            className="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-900/30 dark:text-emerald-100"
        >
            Order by <strong>{deadline}</strong> for advance rates - a{' '}
            {pricing.late_order_surcharge_pct}% surcharge applies to orders
            after that.
        </div>
    );
}

export default function PortalCatalog({
    categories,
    pricing,
    current_order,
}: Props) {
    const [search, setSearch] = useState('');

    const filtered = categories
        .map((cat) => ({
            ...cat,
            items: cat.items.filter((i) =>
                search.trim()
                    ? i.name.toLowerCase().includes(search.toLowerCase()) ||
                      i.sku.toLowerCase().includes(search.toLowerCase())
                    : true,
            ),
        }))
        .filter((cat) => cat.items.length > 0);

    return (
        <>
            <Head title="Catalog" />

            <div className="flex flex-col gap-6">
                <div className="flex items-start justify-between gap-3">
                    <header>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Catalog
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Items, services, and equipment available for your
                            booth.
                        </p>
                    </header>
                    {current_order && (
                        <Link
                            href={`/portal/orders/${current_order.id}`}
                            className="flex items-center gap-2 rounded-lg border-2 border-primary/30 bg-primary/5 px-3 py-2 text-sm hover:border-primary"
                        >
                            <ShoppingBag className="size-4" />
                            <span>
                                Order{' '}
                                <span className="font-mono">
                                    {current_order.order_number}
                                </span>
                                <Badge variant="secondary" className="ml-2">
                                    {current_order.item_count}
                                </Badge>
                            </span>
                        </Link>
                    )}
                </div>

                <PricingBanner pricing={pricing} />

                <Input
                    type="search"
                    placeholder="Search items..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="max-w-md"
                />

                {filtered.map((cat) => (
                    <section key={cat.code}>
                        <div className="mb-2 flex items-center gap-2">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                {cat.name}
                            </h2>
                            {cat.department && (
                                <Badge variant="outline" className="text-xs">
                                    {cat.department}
                                </Badge>
                            )}
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {cat.items.map((item) => (
                                <ItemCard key={item.id} item={item} />
                            ))}
                        </div>
                    </section>
                ))}

                {filtered.length === 0 && (
                    <div className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        No items match "{search}".
                    </div>
                )}
            </div>
        </>
    );
}
