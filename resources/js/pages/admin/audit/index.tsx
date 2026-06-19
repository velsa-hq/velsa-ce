import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type AuditEventRow = {
    id: number;
    created_at: string | null;
    event_type: string;
    subject_type: string | null;
    subject_id: number | null;
    ip: string | null;
    user: { id: number; name: string; email: string } | null;
    venue: { id: number; name: string; slug: string } | null;
    flagged: boolean;
    payload: Record<string, unknown> | null;
};

type UserOption = { id: number; name: string; email: string };
type VenueOption = { id: number; name: string; slug: string };

type Filters = {
    event_type: string | null;
    user_id: number | null;
    venue_id: number | null;
    from: string | null;
    to: string | null;
    flagged?: boolean;
};

type Props = {
    events: {
        data: AuditEventRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: Filters;
    users: UserOption[];
    venues: VenueOption[];
    can_see_raw: boolean;
};

function formatTimestamp(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString();
}

function badgeFor(eventType: string): string {
    const head = eventType.split('.')[0];
    const palette: Record<string, string> = {
        session:
            'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
        user: 'bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-100',
        venue: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
        space: 'bg-teal-100 text-teal-900 dark:bg-teal-900/40 dark:text-teal-100',
        booking:
            'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
        contract:
            'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
        payment:
            'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
        audit: 'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-900/40 dark:text-fuchsia-100',
    };

    return (
        palette[head] ??
        'bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200'
    );
}

export default function AuditIndex({
    events,
    filters,
    users,
    venues,
    can_see_raw,
}: Props) {
    const [draft, setDraft] = useState<Filters>(filters);

    // Keep the filter form in sync when the server-provided filters change
    // (e.g. navigating back/forward to a URL that already carries query params).
    useEffect(() => {
        setDraft(filters);
    }, [filters]);

    const applyFilters = () => {
        router.get('/admin/audit', cleanFilters(draft), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        const empty: Filters = {
            event_type: null,
            user_id: null,
            venue_id: null,
            from: null,
            to: null,
        };
        setDraft(empty);
        router.get(
            '/admin/audit',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const exportUrl = `/admin/audit/export.csv?${new URLSearchParams(
        Object.entries(cleanFilters(draft)).map(([k, v]) => [k, String(v)]),
    ).toString()}`;

    return (
        <>
            <Head title="Audit log · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Audit log
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {events.meta.total.toLocaleString()} events ·
                            payloads {can_see_raw ? 'raw' : 'masked'}
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <a href={exportUrl}>Export CSV</a>
                    </Button>
                </header>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <div className="grid gap-3 md:grid-cols-5">
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            Event type
                            <input
                                type="text"
                                placeholder="session, venue.updated..."
                                value={draft.event_type ?? ''}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        event_type: e.target.value || null,
                                    })
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            User
                            <select
                                value={draft.user_id ?? ''}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        user_id: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            >
                                <option value="">Any user</option>
                                {users.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.email}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            Venue
                            <select
                                value={draft.venue_id ?? ''}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        venue_id: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            >
                                <option value="">Any venue</option>
                                {venues.map((v) => (
                                    <option key={v.id} value={v.id}>
                                        {v.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            From
                            <input
                                type="date"
                                value={draft.from ?? ''}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        from: e.target.value || null,
                                    })
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            />
                        </label>
                        <label className="flex flex-col gap-1 text-xs font-medium">
                            To
                            <input
                                type="date"
                                value={draft.to ?? ''}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        to: e.target.value || null,
                                    })
                                }
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            />
                        </label>
                    </div>
                    <label className="mt-3 flex items-center gap-2 text-xs font-medium">
                        <input
                            type="checkbox"
                            checked={draft.flagged ?? false}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    flagged: e.target.checked || undefined,
                                })
                            }
                        />
                        Flagged only (matches an active audit rule)
                    </label>
                    <div className="mt-3 flex gap-2">
                        <Button size="sm" onClick={applyFilters}>
                            Apply
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={clearFilters}
                        >
                            Clear
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    When
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Event
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    User
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Venue
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Subject
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    IP
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Payload
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No events match these filters.
                                    </td>
                                </tr>
                            ) : (
                                events.data.map((row, idx) => (
                                    <tr
                                        key={row.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 align-top dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 align-top dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2 font-mono text-xs whitespace-nowrap">
                                            {formatTimestamp(row.created_at)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${badgeFor(row.event_type)}`}
                                            >
                                                {row.event_type}
                                            </span>
                                            {row.flagged && (
                                                <span
                                                    title="Matches an active audit rule"
                                                    className="ml-1.5 inline-flex items-center rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold text-rose-900 dark:bg-rose-900/40 dark:text-rose-100"
                                                >
                                                    ⚑ flagged
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {row.user?.email ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {row.venue?.slug ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {row.subject_type
                                                ? `${row.subject_type}#${row.subject_id}`
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {row.ip ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {row.payload ? (
                                                <pre className="max-w-md overflow-x-auto rounded bg-muted/50 px-2 py-1 font-mono text-[10px] break-all whitespace-pre-wrap">
                                                    {JSON.stringify(
                                                        row.payload,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {events.meta.current_page} of{' '}
                        {events.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {events.links.prev ? (
                            <Link
                                href={events.links.prev}
                                preserveScroll
                                className="rounded border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border"
                            >
                                Newer
                            </Link>
                        ) : null}
                        {events.links.next ? (
                            <Link
                                href={events.links.next}
                                preserveScroll
                                className="rounded border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border"
                            >
                                Older
                            </Link>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

function cleanFilters(f: Filters): Record<string, string | number> {
    const out: Record<string, string | number> = {};

    for (const [k, v] of Object.entries(f)) {
        if (v !== null && v !== '' && v !== false && v !== undefined) {
            out[k] = v as string | number;
        }
    }

    return out;
}

AuditIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/audit' },
        { title: 'Audit log', href: '/admin/audit' },
    ],
};
