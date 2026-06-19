import { Link } from '@inertiajs/react';
import { CountingNumber } from '@/components/ui/counting-number';

type Row = { status: string; label: string; count: number };
type Data = { statuses: Row[] };

export function BookingsByStatus({ data }: { data: Data }) {
    return (
        <div className="@container rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="mb-3 flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">
                    Bookings · prior 30 / next 60 days
                </h2>
                <Link
                    href="/bookings"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    view bookings
                </Link>
            </div>
            {/* Columns scale to the tile's own width (container query), not the
                viewport - so the status labels never overflow when the tile sits
                in a narrow ⅓-width slot. */}
            <div className="grid grid-cols-2 gap-2 @[20rem]:grid-cols-3 @[32rem]:grid-cols-6">
                {data.statuses.map((row) => (
                    <div
                        key={row.status}
                        className="flex min-w-0 flex-col gap-1 rounded-lg border border-sidebar-border/40 px-3 py-2 dark:border-sidebar-border/60"
                    >
                        <span className="text-[10px] leading-tight break-words text-muted-foreground uppercase">
                            {row.label}
                        </span>
                        <span className="font-mono text-xl font-semibold tabular-nums">
                            <CountingNumber
                                from={0}
                                to={row.count}
                                duration={0.8}
                            />
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
