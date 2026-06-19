import { Head, Link, router } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    create,
    index,
    update,
    destroy,
} from '@/routes/admin/insurance-certificates';

type Certificate = {
    id: number;
    holder_kind: string;
    holder_name: string;
    policy_type: string;
    carrier: string | null;
    policy_number: string | null;
    coverage_amount_cents: number | null;
    effective_date: string | null;
    expires_on: string;
    status: string;
    status_label: string;
    review_notes: string | null;
    submitted_via_portal: boolean;
    document_url: string | null;
    reviewer: { name: string } | null;
    created_at: string | null;
};

type Props = {
    certificates: {
        data: Certificate[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { status: string | null };
    counts: { pending: number; expiring: number };
};

const FILTERS: { value: string | null; label: string }[] = [
    { value: null, label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'expiring', label: 'Expiring soon' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'expired', label: 'Expired' },
];

const STATUS_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    approved: 'default',
    rejected: 'destructive',
    expired: 'destructive',
};

function formatMoney(cents: number | null): string {
    if (cents === null) {
        return '-';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(cents / 100);
}

function ReviewButtons({ id }: { id: number }) {
    const decide = (status: 'approved' | 'rejected') => {
        const note =
            status === 'rejected'
                ? (window.prompt(
                      'Reason for rejection - shown to the exhibitor in their portal, so avoid confidential or internal-only remarks (optional):',
                  ) ?? '')
                : '';
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
                onClick={() => decide('rejected')}
            >
                Reject
            </Button>
        </div>
    );
}

export default function InsuranceCertificatesIndex({
    certificates,
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

    const remove = (id: number) => {
        if (window.confirm('Delete this certificate? This cannot be undone.')) {
            router.delete(destroy(id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Insurance certificates · Admin" />
            <div className="p-4">
                <div className="mb-4 flex items-center gap-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Insurance certificates
                    </h1>
                    <HelpLink slug="admin/insurance-certificates" />
                    {counts.pending > 0 && (
                        <Badge variant="outline">
                            {counts.pending} pending
                        </Badge>
                    )}
                    {counts.expiring > 0 && (
                        <Badge variant="secondary">
                            {counts.expiring} expiring
                        </Badge>
                    )}
                    <Link href={create().url} className="ml-auto">
                        <Button data-tour-id="coi-new">New certificate</Button>
                    </Link>
                </div>

                <div
                    className="mb-4 flex flex-wrap gap-1"
                    data-tour-id="coi-status-filter"
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

                {certificates.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No certificates.
                    </p>
                ) : (
                    <ul className="space-y-3" data-tour-id="coi-review">
                        {certificates.data.map((c) => (
                            <li
                                key={c.id}
                                className="rounded-lg border border-border bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div className="min-w-0">
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[c.status] ??
                                                    'outline'
                                                }
                                            >
                                                {c.status_label}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {c.holder_kind}
                                            </span>
                                            {c.submitted_via_portal && (
                                                <Badge variant="outline">
                                                    Portal upload
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="font-medium">
                                            {c.holder_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {c.policy_type}
                                            {c.carrier ? ` · ${c.carrier}` : ''}
                                            {c.policy_number
                                                ? ` · #${c.policy_number}`
                                                : ''}
                                        </p>
                                        <div className="mt-1 space-x-3 text-xs text-muted-foreground">
                                            <span>
                                                Coverage:{' '}
                                                {formatMoney(
                                                    c.coverage_amount_cents,
                                                )}
                                            </span>
                                            <span>Expires: {c.expires_on}</span>
                                            {c.reviewer && (
                                                <span>
                                                    Reviewed by{' '}
                                                    {c.reviewer.name}
                                                </span>
                                            )}
                                        </div>
                                        {c.review_notes && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Note: {c.review_notes}
                                            </p>
                                        )}
                                        {c.document_url && (
                                            <a
                                                href={c.document_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="mt-1 inline-block text-xs text-primary underline"
                                            >
                                                View document
                                            </a>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-2">
                                        {c.status === 'pending' && (
                                            <ReviewButtons id={c.id} />
                                        )}
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => remove(c.id)}
                                        >
                                            Delete
                                        </Button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                {(certificates.links.prev || certificates.links.next) && (
                    <div className="mt-4 flex items-center justify-between text-sm">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!certificates.links.prev}
                            onClick={() =>
                                certificates.links.prev &&
                                router.get(
                                    certificates.links.prev,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Previous
                        </Button>
                        <span className="text-muted-foreground">
                            Page {certificates.meta.current_page} of{' '}
                            {certificates.meta.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!certificates.links.next}
                            onClick={() =>
                                certificates.links.next &&
                                router.get(
                                    certificates.links.next,
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

InsuranceCertificatesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/insurance-certificates' },
        {
            title: 'Insurance certificates',
            href: '/admin/insurance-certificates',
        },
    ],
};
