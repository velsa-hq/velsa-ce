import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import ExportModal from './export-modal';
import type { TemplateOption } from './export-modal';
import JournalEntryModal from './journal-entry-modal';
import type { AccountOption, FundOption } from './journal-entry-modal';

type EntryRow = {
    id: number;
    posted_on: string | null;
    account_code: string;
    fund_code: string | null;
    description: string;
    debit_cents: number;
    credit_cents: number;
    venue_name: string | null;
    is_manual: boolean;
    is_reversal: boolean;
    is_reversed: boolean;
    export_batch: { id: number; period: string; status: string } | null;
};

type Batch = {
    id: number;
    period: string;
    status: string;
    entry_count: number;
    debit_total_cents: number;
    credit_total_cents: number;
    balanced: boolean;
    sent_at: string | null;
    acknowledged_at: string | null;
    voided_at: string | null;
};

type VenueOption = { id: number; name: string; slug: string };

type Props = {
    entries: {
        data: EntryRow[];
        meta: { current_page: number; last_page: number; total: number };
        links: { prev: string | null; next: string | null };
    };
    venues: VenueOption[];
    accounts: AccountOption[];
    funds: FundOption[];
    filters: { venue_id: number | null; unexported: boolean };
    summary: { debits_cents: number; credits_cents: number; count: number };
    batches: Batch[];
    export_templates: TemplateOption[];
    current_period: string;
    can_post: boolean;
    can_export: boolean;
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const BATCH_STATUS_COLORS: Record<string, string> = {
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

export default function AccountingIndex({
    entries,
    venues,
    accounts,
    funds,
    filters,
    summary,
    batches,
    export_templates,
    current_period,
    can_post,
    can_export,
}: Props) {
    const [creating, setCreating] = useState(false);
    const [exporting, setExporting] = useState(false);

    const reverse = (row: EntryRow) => {
        if (
            !window.confirm(
                'Reverse this entry? This posts mirror legs; it cannot be undone.',
            )
        ) {
            return;
        }

        router.post(
            `/accounting/journal/${row.id}/reverse`,
            {},
            { preserveScroll: true },
        );
    };

    const onFilter = (
        key: 'venue_id' | 'unexported',
        value: string | boolean,
    ) => {
        const params: Record<string, string> = {};
        const updated = { ...filters, [key]: value };

        if (updated.venue_id) {
            params.venue_id = String(updated.venue_id);
        }

        if (updated.unexported) {
            params.unexported = '1';
        }

        router.get('/accounting', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const balanced = summary.debits_cents === summary.credits_cents;

    return (
        <>
            <Head title="Accounting" />

            {can_post ? (
                <JournalEntryModal
                    open={creating}
                    onClose={() => setCreating(false)}
                    accounts={accounts}
                    funds={funds}
                    venues={venues}
                />
            ) : null}

            {can_export ? (
                <ExportModal
                    open={exporting}
                    onClose={() => setExporting(false)}
                    templates={export_templates}
                    currentPeriod={current_period}
                />
            ) : null}

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Accounting · Journal
                            <HelpLink slug="accounting/journal" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {summary.count} entries ·{' '}
                            {money(summary.debits_cents)} debits /{' '}
                            {money(summary.credits_cents)} credits ·
                            {balanced ? (
                                <span className="ml-1 font-medium text-emerald-700 dark:text-emerald-300">
                                    balanced
                                </span>
                            ) : (
                                <span className="ml-1 font-medium text-rose-700 dark:text-rose-300">
                                    unbalanced
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {can_post ? (
                            <Button
                                data-tour-id="je-new"
                                onClick={() => setCreating(true)}
                            >
                                + New journal entry
                            </Button>
                        ) : null}
                        <Button asChild variant="outline">
                            <Link href="/accounting/trial-balance">
                                Trial balance
                            </Link>
                        </Button>
                        {can_export ? (
                            <Button
                                variant="outline"
                                data-tour-id="gl-export"
                                onClick={() => setExporting(true)}
                            >
                                Export to GL
                            </Button>
                        ) : null}
                    </div>
                </header>

                {batches.length > 0 ? (
                    <section className="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                        <h2 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Recent GL export batches
                        </h2>
                        <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                            {batches.map((b) => (
                                <Link
                                    key={b.id}
                                    href={`/accounting/batches/${b.id}`}
                                    className="flex flex-col gap-1 rounded-lg border border-sidebar-border/70 p-2 text-xs hover:bg-muted dark:border-sidebar-border"
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="font-mono">
                                            {b.period}
                                        </span>
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${BATCH_STATUS_COLORS[b.status] ?? ''}`}
                                        >
                                            {b.status}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between text-muted-foreground">
                                        <span>{b.entry_count} entries</span>
                                        <span>
                                            {money(b.debit_total_cents)}
                                        </span>
                                    </div>
                                    {!b.balanced &&
                                    b.status !== 'empty' &&
                                    b.status !== 'voided' ? (
                                        <span className="text-[10px] font-medium text-rose-700 dark:text-rose-300">
                                            UNBALANCED · DR{' '}
                                            {money(b.debit_total_cents)} / CR{' '}
                                            {money(b.credit_total_cents)}
                                        </span>
                                    ) : null}
                                </Link>
                            ))}
                        </div>
                    </section>
                ) : null}

                <div className="flex flex-wrap gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <select
                        value={filters.venue_id ?? ''}
                        onChange={(e) => onFilter('venue_id', e.target.value)}
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                    >
                        <option value="">All venues</option>
                        {venues.map((v) => (
                            <option key={v.id} value={v.id}>
                                {v.name}
                            </option>
                        ))}
                    </select>
                    <label className="flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={filters.unexported}
                            onChange={(e) =>
                                onFilter('unexported', e.target.checked)
                            }
                        />
                        Only unexported
                    </label>
                </div>

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
                                <th className="px-3 py-2 text-left font-medium">
                                    Batch
                                </th>
                                {can_post ? <th className="px-3 py-2" /> : null}
                            </tr>
                        </thead>
                        <tbody>
                            {entries.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={can_post ? 8 : 7}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No journal entries match.
                                    </td>
                                </tr>
                            ) : (
                                entries.data.map((e, idx) => (
                                    <tr
                                        key={e.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 align-top dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 align-top dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {e.posted_on}
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            <Link
                                                href={`/accounting/accounts/${e.account_code}`}
                                                className="hover:underline"
                                            >
                                                {e.account_code}
                                            </Link>
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
                                        <td className="px-3 py-2 text-xs">
                                            {e.export_batch ? (
                                                <Link
                                                    href={`/accounting/batches/${e.export_batch.id}`}
                                                    className="font-mono hover:underline"
                                                >
                                                    {e.export_batch.period}
                                                </Link>
                                            ) : (
                                                <span className="text-muted-foreground italic">
                                                    unexported
                                                </span>
                                            )}
                                        </td>
                                        {can_post ? (
                                            <td className="px-3 py-2 text-right text-xs">
                                                {e.is_manual &&
                                                !e.is_reversal &&
                                                !e.is_reversed ? (
                                                    <button
                                                        type="button"
                                                        data-tour-id="je-reverse"
                                                        onClick={() =>
                                                            reverse(e)
                                                        }
                                                        className="rounded px-2 py-0.5 text-rose-700 underline hover:text-rose-800 dark:text-rose-300"
                                                    >
                                                        Reverse
                                                    </button>
                                                ) : e.is_reversed ? (
                                                    <span className="text-muted-foreground">
                                                        reversed
                                                    </span>
                                                ) : null}
                                            </td>
                                        ) : null}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {entries.meta.current_page} of{' '}
                        {entries.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {entries.links.prev ? (
                            <Link
                                href={entries.links.prev}
                                preserveScroll
                                className="rounded border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {entries.links.next ? (
                            <Link
                                href={entries.links.next}
                                preserveScroll
                                className="rounded border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border"
                            >
                                Next
                            </Link>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

AccountingIndex.layout = {
    breadcrumbs: [{ title: 'Accounting', href: '/accounting' }],
};
