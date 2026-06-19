import { Link } from '@inertiajs/react';
import { wallClock } from '@/lib/datetime';

type Booking = {
    id: number;
    reference: string | null;
    name: string | null;
    status: string | null;
    client_name: string | null;
    venue_name: string | null;
    start_at: string | null;
    end_at: string | null;
};

type Data = { bookings: Booking[] };

const STATUS_TONE: Record<string, string> = {
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const d = wallClock(iso);

    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function MyUpcomingBookings({ data }: { data: Data }) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">
                    My upcoming bookings · next 14 days
                </h2>
                <Link
                    href="/bookings"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    all bookings
                </Link>
            </div>

            {data.bookings.length === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    No bookings on your plate in the next two weeks.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {data.bookings.map((b) => (
                        <li
                            key={b.id}
                            className="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-muted"
                        >
                            <span className="w-16 shrink-0 font-mono text-[11px] text-muted-foreground">
                                {formatDate(b.start_at)}
                            </span>
                            <Link
                                href={`/bookings/${b.id}`}
                                className="flex-1 truncate text-xs font-medium hover:underline"
                            >
                                {b.name ?? b.reference}
                            </Link>
                            <span className="hidden truncate text-[11px] text-muted-foreground sm:inline">
                                {b.client_name}
                            </span>
                            <span className="hidden truncate text-[11px] text-muted-foreground md:inline">
                                {b.venue_name}
                            </span>
                            <span
                                className={`shrink-0 rounded px-1.5 py-0.5 text-[10px] tracking-wider uppercase ${
                                    STATUS_TONE[b.status ?? ''] ??
                                    'bg-muted text-muted-foreground'
                                }`}
                            >
                                {b.status}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
