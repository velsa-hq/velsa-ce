import { Link } from '@inertiajs/react';

type Entry = {
    id: number;
    event_type: string;
    created_at: string | null;
    user_email: string | null;
    subject_type: string | null;
    subject_id: number | null;
};

type Data = { entries: Entry[] };

const EVENT_TONE: Record<string, string> = {
    session: 'text-blue-700 dark:text-blue-300',
    user: 'text-indigo-700 dark:text-indigo-300',
    venue: 'text-emerald-700 dark:text-emerald-300',
    space: 'text-teal-700 dark:text-teal-300',
    booking: 'text-amber-700 dark:text-amber-300',
    contract: 'text-rose-700 dark:text-rose-300',
    payment: 'text-purple-700 dark:text-purple-300',
    ledger: 'text-cyan-700 dark:text-cyan-300',
};

function eventTone(eventType: string): string {
    const head = eventType.split('.')[0];

    return EVENT_TONE[head] ?? 'text-muted-foreground';
}

function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const diffMs = Date.now() - new Date(iso).getTime();
    const m = Math.floor(diffMs / 60_000);

    if (m < 1) {
        return 'just now';
    }

    if (m < 60) {
        return `${m}m ago`;
    }

    const h = Math.floor(m / 60);

    if (h < 24) {
        return `${h}h ago`;
    }

    const d = Math.floor(h / 24);

    return `${d}d ago`;
}

export function RecentActivity({ data }: { data: Data }) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
            <div className="flex items-baseline justify-between">
                <h2 className="text-sm font-semibold">Recent activity</h2>
                <Link
                    href="/admin/audit"
                    className="text-xs text-muted-foreground hover:underline"
                >
                    audit log
                </Link>
            </div>
            {data.entries.length === 0 ? (
                <p className="py-6 text-center text-xs text-muted-foreground italic">
                    No activity recorded yet.
                </p>
            ) : (
                <ul className="flex flex-col gap-1">
                    {data.entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex items-start gap-2 rounded-md px-2 py-1.5 hover:bg-muted"
                        >
                            <span
                                className={`mt-0.5 font-mono text-xs font-medium ${eventTone(entry.event_type)}`}
                            >
                                {entry.event_type}
                            </span>
                            <div className="flex flex-1 items-center justify-between">
                                <span className="text-xs text-muted-foreground">
                                    {entry.user_email ?? '-'}
                                    {entry.subject_type
                                        ? ` · ${entry.subject_type}#${entry.subject_id}`
                                        : ''}
                                </span>
                                <span className="text-[10px] text-muted-foreground">
                                    {timeAgo(entry.created_at)}
                                </span>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
