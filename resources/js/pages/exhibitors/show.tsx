import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ExhibitorFormModal from './exhibitor-form-modal';
import type { EventOption } from './exhibitor-form-modal';

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
    item_count: number;
    payment_count: number;
};

type Booking = {
    id: number;
    reference: string;
    name: string;
    start_at: string | null;
    end_at: string | null;
};

type Event = {
    id: number;
    name: string;
    portal_slug: string;
    booking: Booking | null;
};

type WorkOrderRow = {
    id: number;
    reference: string;
    title: string;
    department: string | null;
    status: string | null;
    scheduled_for: string | null;
    completed_at: string | null;
};

type Exhibitor = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    phone: string | null;
    booth_assignment: string | null;
    booth_size: string | null;
    address: Record<string, string> | null;
    event: Event | null;
    orders: Order[];
    totals: { total_cents: number; paid_cents: number; balance_cents: number };
    work_orders: WorkOrderRow[];
    work_order_summary: { total: number; completed: number };
};

type Props = { exhibitor: Exhibitor; events: EventOption[] };

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

function formatDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function ExhibitorShow({ exhibitor, events }: Props) {
    const [editing, setEditing] = useState(false);

    const remove = () => {
        if (
            !window.confirm(
                `Delete ${exhibitor.company_name}? This removes the exhibitor and any unpaid orders. This can't be undone.`,
            )
        ) {
            return;
        }

        router.delete(`/exhibitors/${exhibitor.id}`);
    };

    return (
        <>
            <Head title={exhibitor.company_name} />

            <ExhibitorFormModal
                open={editing}
                onClose={() => setEditing(false)}
                events={events}
                mode="edit"
                exhibitor={{
                    id: exhibitor.id,
                    exhibitor_event_id: exhibitor.event?.id ?? null,
                    company_name: exhibitor.company_name,
                    contact_name: exhibitor.contact_name,
                    email: exhibitor.email,
                    phone: exhibitor.phone,
                    booth_assignment: exhibitor.booth_assignment,
                    booth_size: exhibitor.booth_size,
                }}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {exhibitor.company_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {exhibitor.event ? (
                                <>
                                    <Link
                                        href={`/exhibitor-events/${exhibitor.event.id}`}
                                        className="hover:underline"
                                    >
                                        {exhibitor.event.name}
                                    </Link>
                                    {exhibitor.event.booking && (
                                        <>
                                            {' '}
                                            ·{' '}
                                            <Link
                                                href={`/bookings/${exhibitor.event.booking.id}`}
                                                className="hover:underline"
                                            >
                                                {
                                                    exhibitor.event.booking
                                                        .reference
                                                }
                                            </Link>
                                        </>
                                    )}
                                </>
                            ) : (
                                'No event'
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing(true)}
                            data-tour-id="ex-edit"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={remove}
                            className="text-rose-700 hover:text-rose-800 dark:text-rose-300"
                            data-tour-id="ex-delete"
                        >
                            Delete
                        </Button>
                    </div>
                </header>

                <div className="grid gap-4 lg:grid-cols-[1fr_2fr]">
                    {/* Left - contact + booth */}
                    <section className="flex flex-col gap-4">
                        <div className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Contact
                            </h2>
                            <dl className="grid gap-2 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Contact
                                    </dt>
                                    <dd>{exhibitor.contact_name}</dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd>
                                        <a
                                            href={`mailto:${exhibitor.email}`}
                                            className="text-foreground hover:underline"
                                        >
                                            {exhibitor.email}
                                        </a>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd>{exhibitor.phone ?? '-'}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Booth
                            </h2>
                            <dl className="grid gap-2 text-sm">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Assignment
                                    </dt>
                                    <dd className="font-mono">
                                        {exhibitor.booth_assignment ?? '-'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Size
                                    </dt>
                                    <dd>{exhibitor.booth_size ?? '-'}</dd>
                                </div>
                            </dl>
                        </div>

                        <div className="rounded-xl border border-border bg-card p-4">
                            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Account totals
                            </h2>
                            <dl className="grid gap-2 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Total ordered
                                    </dt>
                                    <dd className="tabular-nums">
                                        {money(exhibitor.totals.total_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">
                                        Paid
                                    </dt>
                                    <dd className="text-emerald-700 tabular-nums dark:text-emerald-300">
                                        {money(exhibitor.totals.paid_cents)}
                                    </dd>
                                </div>
                                <div className="flex justify-between border-t border-border pt-2 font-semibold">
                                    <dt>Balance due</dt>
                                    <dd className="tabular-nums">
                                        {money(exhibitor.totals.balance_cents)}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div
                            className="rounded-xl border border-border bg-card p-4"
                            data-tour-id="ex-fulfillment-rollup"
                        >
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Fulfillment
                                </h2>
                                <span className="text-xs text-muted-foreground">
                                    {exhibitor.work_order_summary.completed}/
                                    {exhibitor.work_order_summary.total}{' '}
                                    complete
                                </span>
                            </div>
                            {exhibitor.work_orders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No fulfillment work orders yet - they
                                    generate when an order is confirmed.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {exhibitor.work_orders.map((w) => (
                                        <li key={w.id}>
                                            <Link
                                                href={`/work-orders/${w.id}`}
                                                className="flex items-center justify-between gap-2 hover:underline"
                                            >
                                                <span className="truncate">
                                                    {w.title}
                                                </span>
                                                <Badge
                                                    variant={
                                                        w.status === 'completed'
                                                            ? 'outline'
                                                            : w.status ===
                                                                'cancelled'
                                                              ? 'destructive'
                                                              : 'secondary'
                                                    }
                                                >
                                                    {(
                                                        w.status ?? 'open'
                                                    ).replace(/_/g, ' ')}
                                                </Badge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>

                    {/* Right - orders */}
                    <section>
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                Orders ({exhibitor.orders.length})
                            </h2>
                        </div>
                        <div className="flex flex-col gap-3">
                            {exhibitor.orders.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                                    No orders yet.
                                </div>
                            ) : (
                                exhibitor.orders.map((o) => (
                                    <Link
                                        key={o.id}
                                        href={`/exhibitors/${exhibitor.id}/orders/${o.id}`}
                                        className="block rounded-xl border border-border bg-card p-4 hover:border-primary/40"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex flex-col">
                                                <span className="font-mono text-sm">
                                                    {o.order_number}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {o.item_count} item
                                                    {o.item_count === 1
                                                        ? ''
                                                        : 's'}{' '}
                                                    · placed{' '}
                                                    {formatDate(o.placed_at)}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                {o.status && (
                                                    <Badge
                                                        variant={
                                                            STATUS_VARIANTS[
                                                                o.status
                                                            ] ?? 'secondary'
                                                        }
                                                    >
                                                        {o.status.replace(
                                                            /_/g,
                                                            ' ',
                                                        )}
                                                    </Badge>
                                                )}
                                                <div className="text-right">
                                                    <div className="tabular-nums">
                                                        {money(o.total_cents)}
                                                    </div>
                                                    {o.balance_cents > 0 && (
                                                        <div className="text-xs text-amber-700 tabular-nums dark:text-amber-300">
                                                            {money(
                                                                o.balance_cents,
                                                            )}{' '}
                                                            due
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                ))
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

ExhibitorShow.layout = {
    breadcrumbs: [
        { title: 'Exhibitors', href: '/exhibitors' },
        { title: 'Detail', href: '#' },
    ],
};
