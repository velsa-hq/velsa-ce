import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type EntryRow = {
    id: number;
    posted_on: string | null;
    account_code: string;
    fund_code: string | null;
    description: string;
    debit_cents: number;
    credit_cents: number;
    venue_name: string | null;
};

type Batch = {
    id: number;
    period: string;
    status: string;
    entry_count: number;
    debit_total_cents: number;
    credit_total_cents: number;
    balanced: boolean;
    template_name: string | null;
    created_by: string | null;
    sent_at: string | null;
    acknowledged_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    voided_by: string | null;
    delivery_transport: string | null;
    delivery_detail: string | null;
    failure_reason: string | null;
};

type Props = {
    batch: Batch;
    entries: EntryRow[];
    can_export: boolean;
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    });
}

function date(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString() : '-';
}

const STATUS_COLORS: Record<string, string> = {
    pending:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    ready: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    unbalanced:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    empty: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    sent: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    acknowledged:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    failed: 'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
    voided: 'bg-neutral-300 text-neutral-700 line-through dark:bg-neutral-600 dark:text-neutral-200',
};

export default function BatchDetail({ batch, entries, can_export }: Props) {
    const voidBatch = () => {
        const reason = window.prompt(
            'Void this batch? Its entries detach and return to the pending queue. Enter a reason:',
        );

        if (!reason) {
            return;
        }

        router.post(`/accounting/batches/${batch.id}/void`, { reason });
    };

    const acknowledge = () => {
        if (
            window.confirm(
                'Mark this batch acknowledged? Use this once the GL system has confirmed receipt.',
            )
        ) {
            router.post(`/accounting/batches/${batch.id}/acknowledge`, {});
        }
    };

    const resend = () => {
        router.post(`/accounting/batches/${batch.id}/resend`, {});
    };

    const active = !batch.voided_at;
    const canAcknowledge =
        can_export &&
        active &&
        (batch.status === 'ready' || batch.status === 'sent');
    const canResend =
        can_export &&
        active &&
        ['ready', 'sent', 'failed'].includes(batch.status);

    return (
        <>
            <Head title={`Export ${batch.period} · Batch`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header
                    className="flex flex-wrap items-end justify-between gap-3"
                    data-tour-id="gl-batch"
                >
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            GL export{' '}
                            <span className="font-mono">{batch.period}</span>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[batch.status] ?? ''}`}
                            >
                                {batch.status}
                            </span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {batch.entry_count} entries ·{' '}
                            {money(batch.debit_total_cents)} debits /{' '}
                            {money(batch.credit_total_cents)} credits ·{' '}
                            {batch.balanced ? (
                                <span className="font-medium text-emerald-700 dark:text-emerald-300">
                                    balanced
                                </span>
                            ) : (
                                <span className="font-medium text-rose-700 dark:text-rose-300">
                                    unbalanced
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={`/accounting/batches/${batch.id}/download.csv`}
                            >
                                Download CSV
                            </a>
                        </Button>
                        {canResend ? (
                            <Button
                                variant="outline"
                                data-tour-id="gl-resend"
                                onClick={resend}
                            >
                                Resend
                            </Button>
                        ) : null}
                        {canAcknowledge ? (
                            <Button
                                data-tour-id="gl-acknowledge"
                                onClick={acknowledge}
                            >
                                Mark acknowledged
                            </Button>
                        ) : null}
                        {can_export && active ? (
                            <Button
                                variant="destructive"
                                data-tour-id="gl-void"
                                onClick={voidBatch}
                            >
                                Void batch
                            </Button>
                        ) : null}
                        <Link
                            href="/accounting"
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Journal
                        </Link>
                    </div>
                </header>

                {batch.voided_at ? (
                    <div className="rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm dark:border-rose-900/60 dark:bg-rose-900/20">
                        <span className="font-medium text-rose-800 dark:text-rose-200">
                            Voided {date(batch.voided_at)}
                        </span>
                        {batch.voided_by ? ` by ${batch.voided_by}` : ''}
                        {batch.void_reason ? ` - "${batch.void_reason}"` : ''}.
                        The entries below detached back to the pending queue
                        when this batch was voided.
                    </div>
                ) : null}

                {batch.status === 'failed' && batch.failure_reason ? (
                    <div className="rounded-xl border border-rose-300 bg-rose-50 p-3 text-sm dark:border-rose-900/60 dark:bg-rose-900/20">
                        <span className="font-medium text-rose-800 dark:text-rose-200">
                            Delivery failed
                        </span>{' '}
                        - {batch.failure_reason}. Use <strong>Resend</strong>{' '}
                        once the issue is resolved.
                    </div>
                ) : null}

                <dl className="grid grid-cols-2 gap-3 rounded-xl border border-sidebar-border/70 p-3 text-sm sm:grid-cols-4 dark:border-sidebar-border">
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Template
                        </dt>
                        <dd>{batch.template_name ?? '-'}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Created by
                        </dt>
                        <dd>{batch.created_by ?? '-'}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">Sent</dt>
                        <dd>{date(batch.sent_at)}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-muted-foreground">
                            Acknowledged
                        </dt>
                        <dd>{date(batch.acknowledged_at)}</dd>
                    </div>
                    <div className="col-span-2 sm:col-span-4">
                        <dt className="text-xs text-muted-foreground">
                            Delivery
                        </dt>
                        <dd>
                            {batch.delivery_detail
                                ? batch.delivery_detail
                                : 'Not yet delivered'}
                            {batch.delivery_transport
                                ? ` · via ${batch.delivery_transport}`
                                : ''}
                        </dd>
                    </div>
                </dl>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Date
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Account
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Description
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Venue · Fund
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Debit
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Credit
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        This batch has no entries
                                        {batch.voided_at
                                            ? ' (they were detached on void).'
                                            : '.'}
                                    </td>
                                </tr>
                            ) : (
                                entries.map((e) => (
                                    <tr
                                        key={e.id}
                                        className="border-t border-sidebar-border/40 dark:border-sidebar-border/60"
                                    >
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {e.posted_on}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {e.account_code}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {e.description}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {e.venue_name ?? '-'}
                                            {e.fund_code
                                                ? ` · ${e.fund_code}`
                                                : ''}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {e.debit_cents > 0
                                                ? money(e.debit_cents)
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {e.credit_cents > 0
                                                ? money(e.credit_cents)
                                                : '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

BatchDetail.layout = {
    breadcrumbs: [
        { title: 'Accounting', href: '/accounting' },
        { title: 'Export batch', href: '#' },
    ],
};
