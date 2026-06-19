import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import EventFormModal from './event-form-modal';
import type { BookingOption } from './event-form-modal';
import ExhibitorFormModal from './exhibitor-form-modal';

type Booking = {
    id: number;
    reference: string;
    name: string;
    start_at: string | null;
    end_at: string | null;
};

type ExhibitorRow = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    booth_assignment: string | null;
    booth_size: string | null;
    order_count: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
};

type EventShape = {
    id: number;
    name: string;
    portal_slug: string;
    booking_id: number | null;
    default_booth_size: string | null;
    registration_opens_at: string | null;
    registration_closes_at: string | null;
    advance_rate_deadline: string | null;
    late_order_surcharge_pct: number | null;
    is_registration_open: boolean;
    booking: Booking | null;
    exhibitors: ExhibitorRow[];
};

type Props = { event: EventShape; bookings: BookingOption[] };

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function ExhibitorEventShow({ event, bookings }: Props) {
    const [editing, setEditing] = useState(false);
    const [addingExhibitor, setAddingExhibitor] = useState(false);

    const remove = () => {
        if (
            !window.confirm(
                `Delete the event "${event.name}"? Only allowed when it has no exhibitors.`,
            )
        ) {
            return;
        }

        router.delete(`/exhibitor-events/${event.id}`);
    };

    const totals = event.exhibitors.reduce(
        (acc, e) => ({
            total: acc.total + e.total_cents,
            paid: acc.paid + e.paid_cents,
            balance: acc.balance + e.balance_cents,
        }),
        { total: 0, paid: 0, balance: 0 },
    );

    return (
        <>
            <Head title={`${event.name} · Exhibitors`} />

            <EventFormModal
                open={editing}
                onClose={() => setEditing(false)}
                bookings={bookings}
                mode="edit"
                event={{
                    id: event.id,
                    name: event.name,
                    booking_id: event.booking_id,
                    portal_slug: event.portal_slug,
                    default_booth_size: event.default_booth_size,
                    registration_opens_at: event.registration_opens_at,
                    registration_closes_at: event.registration_closes_at,
                    advance_rate_deadline: event.advance_rate_deadline,
                    late_order_surcharge_pct: event.late_order_surcharge_pct,
                }}
            />
            <ExhibitorFormModal
                open={addingExhibitor}
                onClose={() => setAddingExhibitor(false)}
                events={[{ id: event.id, name: event.name }]}
                mode="create"
                defaultEventId={event.id}
            />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {event.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {event.booking ? (
                                <>
                                    Booking{' '}
                                    <Link
                                        href={`/bookings/${event.booking.id}`}
                                        className="text-foreground hover:underline"
                                    >
                                        {event.booking.reference}
                                    </Link>{' '}
                                    - {event.booking.name}
                                </>
                            ) : (
                                'No linked booking'
                            )}
                            {' · '}
                            Portal slug:{' '}
                            <span className="font-mono">
                                {event.portal_slug}
                            </span>
                            {' · '}
                            {event.is_registration_open ? (
                                <Badge variant="outline">
                                    Registration open
                                </Badge>
                            ) : (
                                <Badge variant="secondary">
                                    Registration closed
                                </Badge>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            onClick={() => setAddingExhibitor(true)}
                        >
                            + New exhibitor
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditing(true)}
                            data-tour-id="ev-edit"
                        >
                            Edit event
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={remove}
                            disabled={event.exhibitors.length > 0}
                            className="text-rose-700 hover:text-rose-800 dark:text-rose-300"
                        >
                            Delete
                        </Button>
                    </div>
                </header>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Exhibitors
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {event.exhibitors.length}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Total ordered
                        </div>
                        <div className="mt-1 text-2xl font-semibold tabular-nums">
                            {money(totals.total)}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Paid
                        </div>
                        <div className="mt-1 text-2xl font-semibold text-emerald-700 tabular-nums dark:text-emerald-300">
                            {money(totals.paid)}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4">
                        <div className="text-xs tracking-wider text-muted-foreground uppercase">
                            Balance due
                        </div>
                        <div className="mt-1 text-2xl font-semibold text-amber-700 tabular-nums dark:text-amber-300">
                            {money(totals.balance)}
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Booth
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Company
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Contact
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Orders
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {event.exhibitors.map((e) => (
                                <tr
                                    key={e.id}
                                    className="border-t border-border/60 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 font-mono">
                                        {e.booth_assignment ?? '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/exhibitors/${e.id}`}
                                            className="font-medium text-foreground hover:underline"
                                        >
                                            {e.company_name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-col">
                                            <span>{e.contact_name}</span>
                                            <span className="text-xs text-muted-foreground">
                                                {e.email}
                                            </span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {e.order_count}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {money(e.total_cents)}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {e.balance_cents > 0 ? (
                                            <span className="text-amber-700 dark:text-amber-300">
                                                {money(e.balance_cents)}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                paid
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {event.exhibitors.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No exhibitors registered yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ExhibitorEventShow.layout = {
    breadcrumbs: [
        { title: 'Exhibitors', href: '/exhibitors' },
        { title: 'Event', href: '#' },
    ],
};
