import { Head, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/admin/support-requests';
import { index } from '@/routes/admin/support-requests';

type Person = { name: string; email: string };

type RequestRow = {
    id: number;
    category: string;
    category_label: string;
    subject: string;
    body: string;
    page_url: string | null;
    app_version: string | null;
    status: string;
    status_label: string;
    created_at: string | null;
    resolved_at: string | null;
    user: (Person & { id: number }) | null;
    resolver: Person | null;
};

type Props = {
    requests: {
        data: RequestRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string | null };
    open_count: number;
};

const STATUS_FILTERS: { value: string | null; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'open', label: 'Open' },
    { value: 'closed', label: 'Closed' },
];

function formatDateTime(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '-';
}

export default function SupportRequestsIndex({
    requests,
    filters,
    open_count,
}: Props) {
    const setStatus = (id: number, status: 'open' | 'closed') => {
        router.put(update(id).url, { status }, { preserveScroll: true });
    };

    const filterTo = (status: string | null) => {
        router.get(index().url, status ? { status } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Support requests · Admin" />
            <div className="p-4">
                <div className="mb-4 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Support requests
                    </h1>
                    <HelpLink slug="help/contact-support" />
                    {open_count > 0 && (
                        <Badge variant="secondary">{open_count} open</Badge>
                    )}
                </div>

                <div
                    className="mb-4 flex gap-1"
                    data-tour-id="support-admin-list"
                >
                    {STATUS_FILTERS.map((f) => (
                        <Button
                            key={f.label}
                            size="sm"
                            variant={
                                (filters.status ?? null) === f.value
                                    ? 'default'
                                    : 'outline'
                            }
                            onClick={() => filterTo(f.value)}
                        >
                            {f.label}
                        </Button>
                    ))}
                </div>

                {requests.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No support requests.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {requests.data.map((r) => (
                            <li
                                key={r.id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge variant="outline">
                                                {r.category_label}
                                            </Badge>
                                            <Badge
                                                variant={
                                                    r.status === 'open'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {r.status_label}
                                            </Badge>
                                        </div>
                                        <p className="font-medium">
                                            {r.subject}
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-wrap text-muted-foreground">
                                            {r.body}
                                        </p>
                                        <div className="mt-2 space-x-3 text-xs text-muted-foreground">
                                            <span>
                                                {r.user
                                                    ? `${r.user.name} (${r.user.email})`
                                                    : 'Unknown user'}
                                            </span>
                                            {r.page_url && (
                                                <span>· {r.page_url}</span>
                                            )}
                                            {r.app_version && (
                                                <span>· v{r.app_version}</span>
                                            )}
                                            <span>
                                                · {formatDateTime(r.created_at)}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="shrink-0">
                                        {r.status === 'open' ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setStatus(r.id, 'closed')
                                                }
                                            >
                                                Mark closed
                                            </Button>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={() =>
                                                    setStatus(r.id, 'open')
                                                }
                                            >
                                                Reopen
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {(requests.links.prev || requests.links.next) && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!requests.links.prev}
                            onClick={() =>
                                requests.links.prev &&
                                router.get(
                                    requests.links.prev,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-muted-foreground">
                            Page {requests.meta.current_page} of{' '}
                            {requests.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!requests.links.next}
                            onClick={() =>
                                requests.links.next &&
                                router.get(
                                    requests.links.next,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

SupportRequestsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/support-requests' },
        { title: 'Support requests', href: '/admin/support-requests' },
    ],
};
