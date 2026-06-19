import { Head, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, update } from '@/routes/admin/exhibitor-permits';

type Permit = {
    id: number;
    exhibitor_name: string;
    permit_type: string;
    details: string;
    status: string;
    status_label: string;
    review_notes: string | null;
    submitted_via_portal: boolean;
    document_url: string | null;
    reviewer: { name: string } | null;
    created_at: string | null;
};

type Props = {
    permits: {
        data: Permit[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string | null };
    counts: { pending: number };
};

const FILTERS: { value: string | null; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'denied', label: 'Denied' },
    { value: 'cancelled', label: 'Cancelled' },
];

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    approved: 'default',
    denied: 'destructive',
    cancelled: 'secondary',
};

function ReviewButtons({ id }: { id: number }) {
    const decide = (status: 'approved' | 'denied') => {
        const note =
            status === 'denied'
                ? (window.prompt(
                      'Reason for denial - shown to the exhibitor in their portal, so avoid confidential or internal-only remarks (optional):',
                  ) ?? '')
                : (window.prompt(
                      'Approval conditions - shown to the exhibitor in their portal, so avoid confidential or internal-only remarks (optional):',
                  ) ?? '');
        router.put(
            update(id).url,
            { status, review_notes: note },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex gap-2">
            <Button size="sm" onClick={() => decide('approved')}>
                Approve
            </Button>
            <Button
                size="sm"
                variant="outline"
                onClick={() => decide('denied')}
            >
                Deny
            </Button>
        </div>
    );
}

export default function ExhibitorPermitsIndex({
    permits,
    filters,
    counts,
}: Props) {
    const filterTo = (status: string | null) => {
        router.get(index().url, status ? { status } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Exhibitor permits · Admin" />
            <div className="p-4">
                <div className="mb-4 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Exhibitor permits
                    </h1>
                    <HelpLink slug="exhibitors/exhibitor-permits" />
                    {counts.pending > 0 && (
                        <Badge variant="outline">
                            {counts.pending} pending
                        </Badge>
                    )}
                </div>

                <div
                    className="mb-4 flex flex-wrap gap-1"
                    data-tour-id="permit-status-filter"
                >
                    {FILTERS.map((f) => (
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

                {permits.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No permit requests.
                    </p>
                ) : (
                    <ul className="space-y-3" data-tour-id="permit-review">
                        {permits.data.map((p) => (
                            <li
                                key={p.id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[p.status] ??
                                                    'outline'
                                                }
                                            >
                                                {p.status_label}
                                            </Badge>
                                            {p.submitted_via_portal && (
                                                <Badge variant="outline">
                                                    Portal request
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="font-medium">
                                            {p.exhibitor_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {p.permit_type}
                                        </p>
                                        <p className="mt-1 text-sm whitespace-pre-line">
                                            {p.details}
                                        </p>
                                        <div className="mt-1 space-x-3 text-xs text-muted-foreground">
                                            {p.reviewer && (
                                                <span>
                                                    Reviewed by{' '}
                                                    {p.reviewer.name}
                                                </span>
                                            )}
                                        </div>
                                        {p.review_notes && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Note: {p.review_notes}
                                            </p>
                                        )}
                                        {p.document_url && (
                                            <a
                                                href={p.document_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-1 inline-block text-xs text-primary underline"
                                            >
                                                View document
                                            </a>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-2">
                                        {p.status === 'pending' && (
                                            <ReviewButtons id={p.id} />
                                        )}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {(permits.links.prev || permits.links.next) && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!permits.links.prev}
                            onClick={() =>
                                permits.links.prev &&
                                router.get(
                                    permits.links.prev,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-muted-foreground">
                            Page {permits.meta.current_page} of{' '}
                            {permits.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!permits.links.next}
                            onClick={() =>
                                permits.links.next &&
                                router.get(
                                    permits.links.next,
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

ExhibitorPermitsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/exhibitor-permits' },
        { title: 'Exhibitor permits', href: '/admin/exhibitor-permits' },
    ],
};
