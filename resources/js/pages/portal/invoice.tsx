import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { wallClock } from '@/lib/datetime';

type Item = {
    sku: string;
    name: string;
    quantity: number;
    unit_price_cents: number;
    line_total_cents: number;
};

type Payment = {
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
    items: Item[];
    payments: Payment[];
};

type Exhibitor = {
    company_name: string;
    contact_name: string;
    email: string;
    phone: string | null;
    booth_assignment: string | null;
    address: Record<string, string> | null;
};

type Event = {
    name: string;
    booking: {
        reference: string;
        name: string;
        venue_name: string | null;
        start_at: string | null;
        end_at: string | null;
    } | null;
} | null;

type Props = { order: Order; exhibitor: Exhibitor; event: Event };

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
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateRange(start: string | null, end: string | null): string {
    if (!start) {
        return '-';
    }

    const opts: Intl.DateTimeFormatOptions = {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    };
    const fmt = (iso: string) =>
        wallClock(iso).toLocaleDateString(undefined, opts);

    if (!end) {
        return fmt(start);
    }

    if (wallClock(start).toDateString() === wallClock(end).toDateString()) {
        return fmt(start);
    }

    return `${fmt(start)} - ${fmt(end)}`;
}

export default function PortalInvoice({ order, exhibitor, event }: Props) {
    return (
        <>
            <Head title={`Invoice ${order.order_number}`} />

            {/* Action bar - hidden on print */}
            <div className="mb-6 flex items-center justify-between border-b border-border bg-card px-4 py-3 print:hidden">
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/portal/orders/${order.id}`}>
                        <ArrowLeft className="size-4" />
                        Back to order
                    </Link>
                </Button>
                <Button onClick={() => window.print()} size="sm">
                    <Printer className="size-4" />
                    Print / save as PDF
                </Button>
            </div>

            {/* Invoice document */}
            <article className="mx-auto max-w-4xl bg-white p-8 text-black print:p-0 print:shadow-none">
                <header className="mb-8 flex items-start justify-between border-b border-neutral-300 pb-6">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            INVOICE
                        </h1>
                        <p className="mt-1 font-mono text-sm text-neutral-600">
                            {order.order_number}
                        </p>
                        {order.placed_at && (
                            <p className="mt-1 text-sm text-neutral-600">
                                Issued {formatDate(order.placed_at)}
                            </p>
                        )}
                    </div>
                    <div className="text-right">
                        <div className="text-lg font-semibold">Velsa</div>
                        {event?.booking?.venue_name && (
                            <div className="mt-1 text-sm text-neutral-600">
                                {event.booking.venue_name}
                            </div>
                        )}
                    </div>
                </header>

                {/* Bill-to + Event meta */}
                <section className="mb-8 grid grid-cols-2 gap-8">
                    <div>
                        <h2 className="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase">
                            Bill to
                        </h2>
                        <div className="font-semibold">
                            {exhibitor.company_name}
                        </div>
                        <div className="text-sm text-neutral-700">
                            {exhibitor.contact_name}
                        </div>
                        <div className="text-sm text-neutral-700">
                            {exhibitor.email}
                        </div>
                        {exhibitor.phone && (
                            <div className="text-sm text-neutral-700">
                                {exhibitor.phone}
                            </div>
                        )}
                        {exhibitor.booth_assignment && (
                            <div className="mt-2 text-sm">
                                Booth:{' '}
                                <span className="font-mono">
                                    {exhibitor.booth_assignment}
                                </span>
                            </div>
                        )}
                    </div>

                    {event && (
                        <div>
                            <h2 className="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase">
                                Event
                            </h2>
                            <div className="font-semibold">{event.name}</div>
                            {event.booking && (
                                <>
                                    <div className="text-sm text-neutral-700">
                                        {event.booking.name}
                                    </div>
                                    <div className="text-sm text-neutral-700">
                                        Ref:{' '}
                                        <span className="font-mono">
                                            {event.booking.reference}
                                        </span>
                                    </div>
                                    <div className="mt-1 text-sm text-neutral-700">
                                        {formatDateRange(
                                            event.booking.start_at,
                                            event.booking.end_at,
                                        )}
                                    </div>
                                </>
                            )}
                        </div>
                    )}
                </section>

                {/* Line items */}
                <table className="w-full text-sm">
                    <thead className="border-y-2 border-neutral-700 bg-neutral-100">
                        <tr>
                            <th className="px-3 py-2 text-left font-semibold">
                                SKU
                            </th>
                            <th className="px-3 py-2 text-left font-semibold">
                                Item
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Qty
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Unit
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {order.items.map((i, idx) => (
                            <tr
                                key={idx}
                                className="border-b border-neutral-200"
                            >
                                <td className="px-3 py-2 font-mono text-xs">
                                    {i.sku}
                                </td>
                                <td className="px-3 py-2">{i.name}</td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {i.quantity}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {money(i.unit_price_cents)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {money(i.line_total_cents)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td
                                colSpan={4}
                                className="px-3 py-2 text-right text-neutral-600"
                            >
                                Subtotal
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {money(order.subtotal_cents)}
                            </td>
                        </tr>
                        <tr>
                            <td
                                colSpan={4}
                                className="px-3 py-2 text-right text-neutral-600"
                            >
                                Tax
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {money(order.tax_cents)}
                            </td>
                        </tr>
                        <tr className="border-t-2 border-neutral-700 text-base font-bold">
                            <td colSpan={4} className="px-3 py-2 text-right">
                                Total
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {money(order.total_cents)}
                            </td>
                        </tr>
                        <tr>
                            <td
                                colSpan={4}
                                className="px-3 py-2 text-right text-neutral-600"
                            >
                                Paid
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                ({money(order.paid_cents)})
                            </td>
                        </tr>
                        <tr className="bg-neutral-100 text-base font-bold">
                            <td colSpan={4} className="px-3 py-2 text-right">
                                Balance due
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {money(order.balance_cents)}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                {order.payments.length > 0 && (
                    <section className="mt-8">
                        <h2 className="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase">
                            Payment history
                        </h2>
                        <ul className="text-sm">
                            {order.payments.map((p, idx) => (
                                <li
                                    key={idx}
                                    className="flex justify-between border-b border-neutral-200 py-1"
                                >
                                    <span>
                                        {p.captured_at &&
                                            formatDate(p.captured_at)}
                                        {' · '}
                                        {p.card_brand ?? 'card'}{' '}
                                        {p.last4 ? `••${p.last4}` : ''}
                                    </span>
                                    <span className="tabular-nums">
                                        {money(p.amount_cents)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <footer className="mt-12 border-t border-neutral-300 pt-4 text-center text-xs text-neutral-500">
                    Thank you for your business. Questions? Contact your event
                    coordinator.
                </footer>
            </article>

            <style>{`
                @media print {
                    body { background: white !important; }
                    nav, header.border-b, footer.border-t { display: none !important; }
                }
            `}</style>
        </>
    );
}
