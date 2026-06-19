import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Calendar, MapPin, ShoppingBag } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { wallClock } from '@/lib/datetime';

type Booking = {
    reference: string;
    name: string;
    start_at: string | null;
    end_at: string | null;
    venue_name: string | null;
};

type Event = {
    name: string;
    registration_closes_at: string | null;
    is_registration_open: boolean;
    booking: Booking | null;
};

type Order = {
    id: number;
    order_number: string;
    status: string | null;
    item_count: number;
    total_cents: number;
    paid_cents?: number;
    balance_cents: number;
    placed_at?: string | null;
};

type Exhibitor = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    phone: string | null;
    booth_assignment: string | null;
    booth_size: string | null;
};

type Props = {
    exhibitor: Exhibitor;
    event: Event | null;
    current_order: Order | null;
    order_history: Order[];
    totals: { total_cents: number; paid_cents: number; balance_cents: number };
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

function formatDateRange(start: string | null, end: string | null): string {
    if (!start) {
        return '-';
    }

    const s = wallClock(start);
    const e = end ? wallClock(end) : null;
    const opts: Intl.DateTimeFormatOptions = {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    };

    if (!e || s.toDateString() === e.toDateString()) {
        return s.toLocaleDateString(undefined, opts);
    }

    return `${s.toLocaleDateString(undefined, opts)} - ${e.toLocaleDateString(undefined, opts)}`;
}

export default function PortalDashboard({
    exhibitor,
    event,
    current_order,
    order_history,
    totals,
}: Props) {
    return (
        <>
            <Head title="Exhibitor Portal" />

            <div className="flex flex-col gap-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Welcome, {exhibitor.contact_name}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {exhibitor.company_name}
                        {exhibitor.booth_assignment && (
                            <>
                                {' · '}
                                Booth{' '}
                                <span className="font-mono">
                                    {exhibitor.booth_assignment}
                                </span>
                            </>
                        )}
                    </p>
                </header>

                {/* Event card */}
                {event && (
                    <section className="rounded-xl border border-border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Your event
                        </h2>
                        <div className="flex flex-col gap-2">
                            <div className="flex items-center gap-2 text-lg font-semibold">
                                {event.name}
                                {event.is_registration_open ? (
                                    <Badge variant="outline">
                                        Registration open
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        Registration closed
                                    </Badge>
                                )}
                            </div>
                            {event.booking && (
                                <div className="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    <span className="flex items-center gap-1.5">
                                        <Calendar className="size-4" />
                                        {formatDateRange(
                                            event.booking.start_at,
                                            event.booking.end_at,
                                        )}
                                    </span>
                                    {event.booking.venue_name && (
                                        <span className="flex items-center gap-1.5">
                                            <MapPin className="size-4" />
                                            {event.booking.venue_name}
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>
                )}

                {/* Action row */}
                <div className="grid gap-4 sm:grid-cols-2">
                    {current_order ? (
                        <Link
                            href={`/portal/orders/${current_order.id}`}
                            className="flex items-center justify-between rounded-xl border-2 border-primary/30 bg-primary/5 p-4 hover:border-primary"
                        >
                            <div>
                                <div className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Open order
                                </div>
                                <div className="mt-1 font-mono text-sm">
                                    {current_order.order_number}
                                </div>
                                <div className="mt-1 text-sm">
                                    {current_order.item_count} item
                                    {current_order.item_count === 1
                                        ? ''
                                        : 's'}{' '}
                                    · {money(current_order.total_cents)}
                                </div>
                            </div>
                            <ArrowRight className="size-5" />
                        </Link>
                    ) : (
                        <Link
                            href="/portal/catalog"
                            className="flex items-center justify-between rounded-xl border-2 border-dashed border-border p-4 hover:border-primary"
                        >
                            <div>
                                <div className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Start a new order
                                </div>
                                <div className="mt-1 text-sm">
                                    Browse the catalog to add items
                                </div>
                            </div>
                            <ShoppingBag className="size-5" />
                        </Link>
                    )}

                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Account
                        </div>
                        <dl className="mt-2 grid gap-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Ordered
                                </dt>
                                <dd className="tabular-nums">
                                    {money(totals.total_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Paid</dt>
                                <dd className="text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {money(totals.paid_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                <dt>Balance due</dt>
                                <dd className="tabular-nums">
                                    {money(totals.balance_cents)}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {/* Order history */}
                <section>
                    <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Your orders
                    </h2>
                    {order_history.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No orders yet - start one from the catalog.
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {order_history.map((o) => (
                                <Link
                                    key={o.id}
                                    href={`/portal/orders/${o.id}`}
                                    className="flex items-center justify-between gap-3 rounded-xl border border-border bg-card p-3 hover:border-primary/40"
                                >
                                    <div className="flex flex-col">
                                        <span className="font-mono text-sm">
                                            {o.order_number}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {o.item_count} item
                                            {o.item_count === 1 ? '' : 's'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {o.status && (
                                            <Badge
                                                variant={
                                                    STATUS_VARIANTS[o.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {o.status.replace(/_/g, ' ')}
                                            </Badge>
                                        )}
                                        <div className="text-right">
                                            <div className="tabular-nums">
                                                {money(o.total_cents)}
                                            </div>
                                            {o.balance_cents > 0 && (
                                                <div className="text-xs text-amber-700 tabular-nums dark:text-amber-300">
                                                    {money(o.balance_cents)} due
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
