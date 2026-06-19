import { Link } from '@inertiajs/react';

type Item = {
    id: number;
    time: string | null;
    duration: number;
    title: string;
    department: string;
    department_label: string;
    booking_name: string | null;
    booking_id: number | null;
};

type Data = { items: Item[] };

const DEPT_DOT: Record<string, string> = {
    setup: 'bg-blue-500',
    av: 'bg-indigo-500',
    catering: 'bg-amber-500',
    security: 'bg-rose-500',
    cleaning: 'bg-emerald-500',
    parking: 'bg-sky-500',
    reception: 'bg-fuchsia-500',
    teardown: 'bg-orange-500',
    ops_lead: 'bg-purple-500',
};

export function TodayOutline({ data }: { data: Data }) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">Today on the board</h2>
                <Link
                    href="/ops/board"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    full ops board
                </Link>
            </div>
            {data.items.length === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    Nothing scheduled in any outline for today.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {data.items.map((item) => (
                        <li
                            key={item.id}
                            className="flex items-start gap-3 rounded-md px-2 py-1.5 hover:bg-muted"
                        >
                            <span className="w-16 shrink-0 font-mono text-xs text-muted-foreground">
                                {item.time}
                            </span>
                            <span
                                className={`mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full ${
                                    DEPT_DOT[item.department] ??
                                    'bg-neutral-400'
                                }`}
                                title={item.department_label}
                            />
                            <div className="flex flex-col gap-0.5">
                                <Link
                                    href={`/bookings/${item.booking_id}/outline`}
                                    className="text-xs font-medium hover:underline"
                                >
                                    {item.title}
                                </Link>
                                <span className="text-[10px] text-muted-foreground">
                                    {item.department_label} · {item.duration}m ·{' '}
                                    {item.booking_name}
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
