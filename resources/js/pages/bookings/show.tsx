import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import RecordDocuments from '@/components/record-documents';
import type { RecordDocument } from '@/components/record-documents';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { wallClock } from '@/lib/datetime';
import {
    diagram,
    edit,
    index,
    outline as outlineRoute,
} from '@/routes/bookings';
import { draft as draftContract } from '@/routes/bookings/contracts';
import { store as storeNarrative } from '@/routes/bookings/narratives';
import { send as sendContract, show as contractShow } from '@/routes/contracts';

type Space = {
    id: number;
    name: string | null;
    kind: string | null;
    capacity: number | null;
    start_at: string | null;
    end_at: string | null;
};

type Booking = {
    id: number;
    reference: string;
    name: string;
    kind: string | null;
    status: string;
    start_at: string | null;
    end_at: string | null;
    total_cents: number;
    attendance_estimate: number | null;
    notes: string | null;
    cancelled_at: string | null;
    cancel_reason: string | null;
    venue: { id: number; name: string; slug: string } | null;
    client: { id: number; name: string; type: string | null } | null;
    owner: { id: number; name: string; email: string } | null;
    spaces: Space[];
};

type ContractSummary = {
    id: number;
    reference: string;
    status: string;
    total_cents: number;
    sent_at: string | null;
    signed_at: string | null;
};

type OutlineSummary = {
    id: number;
    published_version: number;
    published_at: string | null;
    items_count: number;
} | null;

type BillingInvoice = {
    id: number;
    number: string;
    kind: string | null;
    status: string | null;
    status_label: string | null;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    issued_on: string | null;
    due_on: string | null;
};

type ScheduleInstallment = {
    id: number;
    sequence: number;
    due_date: string | null;
    amount_cents: number;
    label: string | null;
    invoice_id: number | null;
    invoice_number: string | null;
    invoiced_at: string | null;
    paid_at: string | null;
};

type PaymentSchedule = {
    id: number;
    total_cents: number;
    installments: ScheduleInstallment[];
};

type Billing = {
    deposit_percent: number;
    invoiced_cents: number;
    remaining_to_invoice_cents: number;
    invoices: BillingInvoice[];
    payment_schedule: PaymentSchedule | null;
};

type Narrative = {
    id: number;
    kind: string | null;
    kind_label: string | null;
    body: string;
    happened_at: string | null;
    created_at: string | null;
    author: { id: number; name: string } | null;
};

type NarrativeKindOption = {
    value: string;
    label: string;
};

type StaffAssignmentRow = {
    id: number;
    role: string;
    start_at: string | null;
    end_at: string | null;
    hourly_rate_cents: number;
    duration_hours: number;
    labor_cost_cents: number;
    notes: string | null;
    user: { id: number; name: string; email: string } | null;
};

type StaffCandidate = { id: number; name: string; email: string };

type Props = {
    booking: Booking;
    contracts: ContractSummary[];
    outline: OutlineSummary;
    billing: Billing;
    narratives: Narrative[];
    narrative_kinds: NarrativeKindOption[];
    staff: StaffAssignmentRow[];
    staff_candidates: StaffCandidate[];
    documents: RecordDocument[];
};

const STATUS_COLORS: Record<string, string> = {
    inquiry:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function formatDateTime(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function formatRange(start: string | null, end: string | null): string {
    if (!start || !end) {
        return '-';
    }

    const s = wallClock(start);
    const e = wallClock(end);
    const sameDay = s.toDateString() === e.toDateString();
    const dateFmt = (d: Date, opts: Intl.DateTimeFormatOptions) =>
        new Intl.DateTimeFormat(undefined, opts).format(d);
    const time = (d: Date) =>
        dateFmt(d, { hour: 'numeric', minute: '2-digit' });

    if (sameDay) {
        return `${dateFmt(s, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })} · ${time(s)} - ${time(e)}`;
    }

    return `${dateFmt(s, { month: 'short', day: 'numeric' })} ${time(s)} - ${dateFmt(e, { month: 'short', day: 'numeric', year: 'numeric' })} ${time(e)}`;
}

export default function BookingsShow({
    booking,
    contracts,
    outline,
    billing,
    narratives,
    narrative_kinds,
    staff,
    staff_candidates,
    documents,
}: Props) {
    const [drafting, setDrafting] = useState(false);
    const [sendingId, setSendingId] = useState<number | null>(null);
    const canDraft = booking.status !== 'cancelled';

    const onDraftContract = () => {
        router.post(
            draftContract(booking.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setDrafting(true),
                onFinish: () => setDrafting(false),
            },
        );
    };

    const onSendContract = (contract: ContractSummary) => {
        if (!booking.client?.name) {
            alert('Booking has no client to send to.');

            return;
        }

        const name = booking.client.name;
        const email = window.prompt(
            `Send contract ${contract.reference} to which email for ${name}?`,
            'client@example.test',
        );

        if (!email) {
            return;
        }

        router.post(
            sendContract(contract.id).url,
            { signers: [{ name, email, role: 'client' }] },
            {
                preserveScroll: true,
                onStart: () => setSendingId(contract.id),
                onFinish: () => setSendingId(null),
            },
        );
    };

    return (
        <>
            <Head title={`${booking.reference} · ${booking.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {booking.name}
                            </h1>
                            <span
                                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[booking.status] ?? ''}`}
                            >
                                {booking.status}
                            </span>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span className="font-mono">
                                {booking.reference}
                            </span>
                            {booking.kind ? (
                                <>
                                    <span>·</span>
                                    <span>
                                        {booking.kind.replace('_', ' ')}
                                    </span>
                                </>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={edit(booking.id).url}
                                data-tour-id="bk-edit-btn"
                            >
                                Edit
                            </Link>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-tour-id="bk-rebook-clone"
                            onClick={() =>
                                router.post(
                                    `/bookings/${booking.id}/clone`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Re-book (clone)
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link
                                href={diagram(booking.id).url}
                                data-tour-id="bk-floor-plan-link"
                            >
                                Floor plan
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={outlineRoute(booking.id).url}>
                                Run-of-show
                            </Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={`/bookings/${booking.id}/settlement.pdf`}
                                target="_blank"
                                rel="noopener"
                                data-tour-id="bk-settlement-pdf"
                            >
                                Settlement PDF
                            </a>
                        </Button>
                    </div>
                </header>

                {booking.status === 'cancelled' && booking.cancel_reason ? (
                    <Card className="border-rose-300 bg-rose-50/50 dark:border-rose-900 dark:bg-rose-950/30">
                        <CardContent className="p-4 text-sm">
                            <div className="font-medium text-rose-900 dark:text-rose-200">
                                Cancelled
                                {booking.cancelled_at
                                    ? ` on ${formatDateTime(booking.cancelled_at)}`
                                    : ''}
                            </div>
                            <div className="mt-1 text-rose-800 dark:text-rose-300">
                                {booking.cancel_reason}
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Detail
                            label="When"
                            value={formatRange(
                                booking.start_at,
                                booking.end_at,
                            )}
                            className="sm:col-span-2"
                        />
                        <Detail
                            label="Venue"
                            value={booking.venue ? booking.venue.name : '-'}
                            href={
                                booking.venue
                                    ? `/venues/${booking.venue.slug}`
                                    : undefined
                            }
                        />
                        <Detail
                            label="Client"
                            value={booking.client ? booking.client.name : '-'}
                            sub={booking.client?.type ?? null}
                            href={
                                booking.client
                                    ? `/clients/${booking.client.id}`
                                    : undefined
                            }
                        />
                        <Detail
                            label="Total"
                            value={
                                booking.total_cents
                                    ? money(booking.total_cents)
                                    : '-'
                            }
                        />
                        <Detail
                            label="Attendance"
                            value={
                                booking.attendance_estimate?.toLocaleString() ??
                                '-'
                            }
                        />
                        <Detail
                            label="Owner"
                            value={booking.owner?.name ?? '-'}
                            sub={booking.owner?.email ?? null}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <h2 className="text-sm font-semibold">Spaces</h2>
                        {booking.spaces.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No spaces attached.
                            </p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {booking.spaces.map((s) => (
                                    <div
                                        key={s.id}
                                        className="rounded-md border border-border p-3 text-sm"
                                    >
                                        <div className="font-medium">
                                            {s.name ?? '-'}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {s.kind?.replace('_', ' ') ?? ''}
                                            {s.capacity
                                                ? ` · cap ${s.capacity}`
                                                : ''}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {booking.notes ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">Notes</h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {booking.notes}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card data-tour-id="bk-contracts-card">
                        <CardContent className="flex flex-col gap-3 p-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-sm font-semibold">
                                    Contracts
                                </h2>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        {contracts.length}
                                    </span>
                                    {canDraft && (
                                        <Button
                                            type="button"
                                            onClick={onDraftContract}
                                            disabled={drafting}
                                            size="sm"
                                            data-tour-id="bk-draft-contract"
                                        >
                                            {drafting && <Spinner />}+ Draft
                                            contract
                                        </Button>
                                    )}
                                </div>
                            </div>
                            {contracts.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No contracts drafted yet.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2 text-sm">
                                    {contracts.map((c) => (
                                        <li
                                            key={c.id}
                                            className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                        >
                                            <Link
                                                href={contractShow(c.id).url}
                                                data-tour-id="bk-contract-row"
                                                className="min-w-0 flex-1 hover:underline"
                                            >
                                                <div className="font-mono text-xs">
                                                    {c.reference}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {c.status.replace('_', ' ')}
                                                    {c.sent_at
                                                        ? ` · sent ${formatDateTime(c.sent_at)}`
                                                        : ''}
                                                </div>
                                            </Link>
                                            <span className="font-mono text-xs">
                                                {money(c.total_cents)}
                                            </span>
                                            {c.status === 'draft' ? (
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        onSendContract(c)
                                                    }
                                                    disabled={
                                                        sendingId === c.id
                                                    }
                                                    size="sm"
                                                    data-tour-id="bk-send-contract"
                                                >
                                                    {sendingId === c.id && (
                                                        <Spinner />
                                                    )}
                                                    Send
                                                </Button>
                                            ) : null}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <h2 className="text-sm font-semibold">
                                Run-of-show
                            </h2>
                            {outline ? (
                                <div className="text-sm">
                                    <div>
                                        <span className="font-medium">
                                            {outline.items_count}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            items
                                        </span>
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        Version {outline.published_version}
                                        {outline.published_at
                                            ? ` · published ${formatDateTime(outline.published_at)}`
                                            : ' · unpublished'}
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No outline yet.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <BillingPanel booking={booking} billing={billing} />

                <StaffPanel
                    booking={booking}
                    staff={staff}
                    candidates={staff_candidates}
                />

                <NarrativePanel
                    booking={booking}
                    narratives={narratives}
                    kinds={narrative_kinds}
                />

                <RecordDocuments
                    documents={documents}
                    storeUrl={`/bookings/${booking.id}/documents`}
                    destroyUrl={(id) =>
                        `/bookings/${booking.id}/documents/${id}`
                    }
                />

                <div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to bookings
                    </Link>
                </div>
            </div>
        </>
    );
}

function NarrativePanel({
    booking,
    narratives,
    kinds,
}: {
    booking: Booking;
    narratives: Narrative[];
    kinds: NarrativeKindOption[];
}) {
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState(kinds[0]?.value ?? 'note');
    const [body, setBody] = useState('');
    const [happenedAt, setHappenedAt] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const reset = () => {
        setBody('');
        setHappenedAt('');
        setKind(kinds[0]?.value ?? 'note');
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!body.trim()) {
            return;
        }

        setSubmitting(true);
        router.post(
            storeNarrative(booking.id).url,
            {
                kind,
                body,
                happened_at: happenedAt || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    setOpen(false);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Card>
            <CardContent className="flex flex-col gap-3 p-4">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold">Event narrative</h2>
                    <div className="flex items-center gap-2">
                        <span className="text-xs text-muted-foreground">
                            {narratives.length}{' '}
                            {narratives.length === 1 ? 'entry' : 'entries'}
                        </span>
                        <Button
                            type="button"
                            variant={open ? 'outline' : 'default'}
                            size="sm"
                            onClick={() => setOpen((s) => !s)}
                            data-tour-id="booking-narrative-add"
                        >
                            {open ? 'Cancel' : '+ Add entry'}
                        </Button>
                    </div>
                </div>

                {open ? (
                    <form
                        onSubmit={submit}
                        className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-2"
                        aria-label="Add narrative entry"
                    >
                        <label className="flex flex-col gap-1">
                            <span className="text-xs text-muted-foreground">
                                Kind
                            </span>
                            <select
                                value={kind}
                                onChange={(e) => setKind(e.target.value)}
                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                            >
                                {kinds.map((k) => (
                                    <option key={k.value} value={k.value}>
                                        {k.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-xs text-muted-foreground">
                                Happened at{' '}
                                <span className="text-muted-foreground/70">
                                    (defaults to now)
                                </span>
                            </span>
                            <input
                                type="datetime-local"
                                value={happenedAt}
                                onChange={(e) => setHappenedAt(e.target.value)}
                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                            />
                        </label>
                        <label className="flex flex-col gap-1 sm:col-span-2">
                            <span className="text-xs text-muted-foreground">
                                What happened
                            </span>
                            <textarea
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                required
                                maxLength={5000}
                                rows={4}
                                placeholder="Client called to confirm AV requirements. They want 2 lavaliers and a confidence monitor at the podium."
                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                            />
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="submit"
                                disabled={submitting || !body.trim()}
                            >
                                {submitting && <Spinner />}
                                Append entry
                            </Button>
                        </div>
                    </form>
                ) : null}

                {narratives.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No entries yet. Add the first one to start the booking's
                        history.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-2 text-sm">
                        {narratives.map((n) => (
                            <li
                                key={n.id}
                                className="flex flex-col gap-1 rounded-md border border-border p-3"
                            >
                                <div className="flex items-center justify-between gap-3 text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 font-medium">
                                            {n.kind_label ?? n.kind ?? '-'}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDateTime(n.happened_at)}
                                        </span>
                                    </div>
                                    <span className="text-muted-foreground">
                                        {n.author?.name ?? 'system'}
                                    </span>
                                </div>
                                <p className="text-sm whitespace-pre-wrap">
                                    {n.body}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function Detail({
    label,
    value,
    sub,
    href,
    className,
}: {
    label: string;
    value: string;
    sub?: string | null;
    href?: string;
    className?: string;
}) {
    return (
        <div className={className ?? ''}>
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-medium">
                {href ? (
                    <Link href={href} className="hover:underline">
                        {value}
                    </Link>
                ) : (
                    value
                )}
            </div>
            {sub ? (
                <div className="text-xs text-muted-foreground">{sub}</div>
            ) : null}
        </div>
    );
}

function BillingPanel({
    booking,
    billing,
}: {
    booking: Booking;
    billing: Billing;
}) {
    const issueDeposit = () => {
        router.post(`/admin/bookings/${booking.id}/invoices/deposit`);
    };
    const issueBalance = () => {
        router.post(`/admin/bookings/${booking.id}/invoices/balance`);
    };

    const hasDeposit = billing.invoices.some((i) => i.kind === 'deposit');
    const hasBalance = billing.invoices.some((i) => i.kind === 'balance');

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base">Billing</CardTitle>
                    <span className="text-xs text-muted-foreground">
                        {billing.deposit_percent}% deposit on file
                    </span>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-3 gap-3 text-sm">
                    <div className="rounded-md border border-border bg-muted/30 p-3">
                        <div className="text-xs text-muted-foreground">
                            Booking total
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(booking.total_cents)}
                        </div>
                    </div>
                    <div className="rounded-md border border-border bg-muted/30 p-3">
                        <div className="text-xs text-muted-foreground">
                            Invoiced
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(billing.invoiced_cents)}
                        </div>
                    </div>
                    <div className="rounded-md border border-border bg-muted/30 p-3">
                        <div className="text-xs text-muted-foreground">
                            Remaining
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(billing.remaining_to_invoice_cents)}
                        </div>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        onClick={issueDeposit}
                        disabled={hasDeposit}
                        size="sm"
                    >
                        {hasDeposit
                            ? 'Deposit invoice issued'
                            : 'Issue deposit invoice'}
                    </Button>
                    <Button
                        type="button"
                        onClick={issueBalance}
                        disabled={
                            billing.remaining_to_invoice_cents <= 0 ||
                            hasBalance
                        }
                        size="sm"
                        variant="outline"
                    >
                        {hasBalance
                            ? 'Balance invoice issued'
                            : 'Issue balance invoice'}
                    </Button>
                </div>

                {billing.invoices.length > 0 && (
                    <div className="mt-4 overflow-hidden rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Invoice
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Kind
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Total
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {billing.invoices.map((inv) => (
                                    <tr
                                        key={inv.id}
                                        className="border-t border-border/60"
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/admin/invoices/${inv.number}`}
                                                className="font-mono text-xs hover:underline"
                                            >
                                                {inv.number}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {inv.kind ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {inv.status_label}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {money(inv.total_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">
                                            {money(inv.balance_cents)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <PaymentSchedulePanel
                    booking={booking}
                    schedule={billing.payment_schedule}
                    depositPercent={billing.deposit_percent}
                />
            </CardContent>
        </Card>
    );
}

function PaymentSchedulePanel({
    booking,
    schedule,
    depositPercent,
}: {
    booking: Booking;
    schedule: PaymentSchedule | null;
    depositPercent: number;
}) {
    const blocked = depositPercent > 0;

    return (
        <div className="mt-6 rounded-lg border border-border p-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Payment schedule</h3>
                {schedule && (
                    <span className="text-xs text-muted-foreground tabular-nums">
                        Scheduled total {money(schedule.total_cents)} ·{' '}
                        {schedule.installments.length} installment(s)
                    </span>
                )}
            </div>

            {blocked && !schedule && (
                <p className="mt-2 text-xs text-muted-foreground">
                    Set deposit percent to 0 on this booking to switch from the
                    two-phase deposit/balance flow to an installment schedule.
                </p>
            )}

            {!schedule && !blocked && (
                <PaymentScheduleForm
                    booking={booking}
                    initial={null}
                    onCancel={null}
                />
            )}

            {schedule && (
                <PaymentScheduleTable booking={booking} schedule={schedule} />
            )}
        </div>
    );
}

function PaymentScheduleTable({
    booking,
    schedule,
}: {
    booking: Booking;
    schedule: PaymentSchedule;
}) {
    const [editing, setEditing] = useState(false);

    if (editing) {
        return (
            <PaymentScheduleForm
                booking={booking}
                initial={schedule}
                onCancel={() => setEditing(false)}
            />
        );
    }

    const onDelete = () => {
        if (
            !confirm(
                'Delete the payment schedule? Un-invoiced installments will be removed.',
            )
        ) {
            return;
        }

        router.delete(`/payment-schedules/${schedule.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="mt-3 overflow-hidden rounded-md border border-border">
            <table className="w-full text-sm">
                <thead className="bg-muted/40 text-xs tracking-wider text-muted-foreground uppercase">
                    <tr>
                        <th className="px-3 py-2 text-left font-medium">#</th>
                        <th className="px-3 py-2 text-left font-medium">
                            Label
                        </th>
                        <th className="px-3 py-2 text-left font-medium">Due</th>
                        <th className="px-3 py-2 text-right font-medium">
                            Amount
                        </th>
                        <th className="px-3 py-2 text-left font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {schedule.installments.map((inst) => (
                        <tr
                            key={inst.id}
                            className="border-t border-border/60 align-top"
                        >
                            <td className="px-3 py-2 tabular-nums">
                                {inst.sequence}
                            </td>
                            <td className="px-3 py-2 text-muted-foreground">
                                {inst.label ?? '-'}
                            </td>
                            <td className="px-3 py-2 tabular-nums">
                                {inst.due_date ?? '-'}
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">
                                {money(inst.amount_cents)}
                            </td>
                            <td className="px-3 py-2 text-xs">
                                {inst.paid_at ? (
                                    <span className="rounded-md bg-emerald-100 px-1.5 py-0.5 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200">
                                        Paid
                                    </span>
                                ) : inst.invoice_number ? (
                                    <Link
                                        href={`/admin/invoices/${inst.invoice_number}`}
                                        className="font-mono text-xs hover:underline"
                                    >
                                        Invoiced · {inst.invoice_number}
                                    </Link>
                                ) : (
                                    <span className="text-muted-foreground">
                                        Pending due date
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="flex justify-end gap-2 border-t border-border/60 bg-muted/20 px-3 py-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => setEditing(true)}
                    data-tour-id="bk-payment-schedule-edit"
                >
                    Edit schedule
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={onDelete}
                    data-tour-id="bk-payment-schedule-delete"
                >
                    Delete schedule
                </Button>
            </div>
        </div>
    );
}

function PaymentScheduleForm({
    booking,
    initial,
    onCancel,
}: {
    booking: Booking;
    initial: PaymentSchedule | null;
    onCancel: (() => void) | null;
}) {
    const [rows, setRows] = useState<
        Array<{
            sequence: number;
            due_date: string;
            amount_cents: number;
            label: string;
            invoiced: boolean;
        }>
    >(() => {
        if (initial) {
            return initial.installments.map((i) => ({
                sequence: i.sequence,
                due_date: i.due_date ?? '',
                amount_cents: i.amount_cents,
                label: i.label ?? '',
                invoiced: i.invoice_id !== null,
            }));
        }

        const half = Math.round(booking.total_cents / 2);

        return [
            {
                sequence: 1,
                due_date: '',
                amount_cents: half,
                label: 'Deposit',
                invoiced: false,
            },
            {
                sequence: 2,
                due_date: '',
                amount_cents: booking.total_cents - half,
                label: 'Balance',
                invoiced: false,
            },
        ];
    });

    const total = rows.reduce((sum, r) => sum + (r.amount_cents || 0), 0);
    const targetTotal = booking.total_cents;
    const totalMatches = total === targetTotal;

    const addRow = () => {
        const nextSeq =
            rows.length > 0 ? Math.max(...rows.map((r) => r.sequence)) + 1 : 1;
        setRows([
            ...rows,
            {
                sequence: nextSeq,
                due_date: '',
                amount_cents: 0,
                label: '',
                invoiced: false,
            },
        ]);
    };

    const removeRow = (seq: number) => {
        setRows(rows.filter((r) => r.sequence !== seq));
    };

    const setField = (seq: number, patch: Partial<(typeof rows)[number]>) => {
        setRows(rows.map((r) => (r.sequence === seq ? { ...r, ...patch } : r)));
    };

    const submit = () => {
        router.put(
            `/bookings/${booking.id}/payment-schedule`,
            {
                installments: rows.map((r) => ({
                    sequence: r.sequence,
                    due_date: r.due_date,
                    amount_cents: r.amount_cents,
                    label: r.label || undefined,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => onCancel?.(),
            },
        );
    };

    return (
        <div className="mt-3 rounded-md border border-border p-3">
            <div className="overflow-hidden rounded-md border border-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/40 text-xs tracking-wider text-muted-foreground uppercase">
                        <tr>
                            <th className="px-2 py-2 text-left font-medium">
                                #
                            </th>
                            <th className="px-2 py-2 text-left font-medium">
                                Label
                            </th>
                            <th className="px-2 py-2 text-left font-medium">
                                Due date
                            </th>
                            <th className="px-2 py-2 text-right font-medium">
                                Amount ($)
                            </th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr
                                key={r.sequence}
                                className="border-t border-border/60"
                            >
                                <td className="px-2 py-1 tabular-nums">
                                    {r.sequence}
                                </td>
                                <td className="px-2 py-1">
                                    <input
                                        type="text"
                                        value={r.label}
                                        disabled={r.invoiced}
                                        onChange={(e) =>
                                            setField(r.sequence, {
                                                label: e.target.value,
                                            })
                                        }
                                        className="w-full rounded-md border border-border bg-background px-2 py-1 text-xs"
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <input
                                        type="date"
                                        value={r.due_date}
                                        disabled={r.invoiced}
                                        onChange={(e) =>
                                            setField(r.sequence, {
                                                due_date: e.target.value,
                                            })
                                        }
                                        className="rounded-md border border-border bg-background px-2 py-1 text-xs"
                                    />
                                </td>
                                <td className="px-2 py-1 text-right">
                                    <input
                                        type="number"
                                        min={0}
                                        step={0.01}
                                        value={(r.amount_cents / 100).toFixed(
                                            2,
                                        )}
                                        disabled={r.invoiced}
                                        onChange={(e) =>
                                            setField(r.sequence, {
                                                amount_cents: Math.round(
                                                    Number(e.target.value) *
                                                        100,
                                                ),
                                            })
                                        }
                                        className="w-28 rounded-md border border-border bg-background px-2 py-1 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td className="px-2 py-1">
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => removeRow(r.sequence)}
                                        disabled={r.invoiced}
                                    >
                                        Remove
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={addRow}
                    data-tour-id="bk-payment-schedule-add-installment"
                >
                    Add installment
                </Button>
                <div className="text-xs">
                    Scheduled {money(total)} of {money(targetTotal)}{' '}
                    {!totalMatches && (
                        <span className="ml-2 rounded-md bg-amber-100 px-1.5 py-0.5 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
                            doesn't match booking total
                        </span>
                    )}
                </div>
                <div className="flex gap-2">
                    {onCancel && (
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Cancel
                        </Button>
                    )}
                    <Button
                        type="button"
                        size="sm"
                        onClick={submit}
                        data-tour-id="bk-payment-schedule-save"
                    >
                        Save schedule
                    </Button>
                </div>
            </div>
        </div>
    );
}

function StaffPanel({
    booking,
    staff,
    candidates,
}: {
    booking: Booking;
    staff: StaffAssignmentRow[];
    candidates: StaffCandidate[];
}) {
    const [showForm, setShowForm] = useState(false);

    const totalLabor = staff.reduce((sum, s) => sum + s.labor_cost_cents, 0);
    const totalHours = staff.reduce((sum, s) => sum + s.duration_hours, 0);

    const onDelete = (assignment: StaffAssignmentRow) => {
        if (
            !confirm(
                `Remove ${assignment.user?.name ?? 'this assignment'} from this event?`,
            )
        ) {
            return;
        }

        router.delete(`/staff-assignments/${assignment.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-base">Staff</CardTitle>
                    <div className="flex items-center gap-3">
                        <span className="text-xs text-muted-foreground tabular-nums">
                            {staff.length} assignment(s) ·{' '}
                            {totalHours.toFixed(1)} hrs · {money(totalLabor)}{' '}
                            est. labor
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setShowForm((s) => !s)}
                            data-tour-id="booking-staff-add"
                        >
                            {showForm ? 'Cancel' : 'Add assignment'}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {showForm && (
                    <StaffAssignmentForm
                        booking={booking}
                        candidates={candidates}
                        onClose={() => setShowForm(false)}
                    />
                )}

                {staff.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No staff assigned yet. Add an assignment to roster
                        someone for this event - they'll show up as a candidate
                        when picking a responsible user for outline items.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-md border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/40 text-xs tracking-wider text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Person
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Role
                                    </th>
                                    <th className="px-3 py-2 text-left font-medium">
                                        Shift
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Hours
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Rate
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Est. cost
                                    </th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {staff.map((a) => (
                                    <tr
                                        key={a.id}
                                        className="border-t border-border/60"
                                    >
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {a.user?.name ?? 'Unknown'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {a.user?.email}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {a.role}
                                        </td>
                                        <td className="px-3 py-2 text-xs tabular-nums">
                                            {formatRange(a.start_at, a.end_at)}
                                        </td>
                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                            {a.duration_hours.toFixed(1)}
                                        </td>
                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                            {money(a.hourly_rate_cents)}/hr
                                        </td>
                                        <td className="px-3 py-2 text-right text-xs tabular-nums">
                                            {money(a.labor_cost_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => onDelete(a)}
                                                data-tour-id="booking-staff-remove"
                                            >
                                                Remove
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function StaffAssignmentForm({
    booking,
    candidates,
    onClose,
}: {
    booking: Booking;
    candidates: StaffCandidate[];
    onClose: () => void;
}) {
    const defaultStart = booking.start_at ? booking.start_at.slice(0, 16) : '';
    const defaultEnd = booking.end_at ? booking.end_at.slice(0, 16) : '';

    const [form, setForm] = useState({
        user_id: candidates[0]?.id ?? 0,
        role: 'Event lead',
        start_at: defaultStart,
        end_at: defaultEnd,
        hourly_rate_cents: 3500,
        notes: '',
    });

    const submit = () => {
        router.post(
            `/bookings/${booking.id}/staff`,
            {
                ...form,
                notes: form.notes || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <div className="grid gap-3 rounded-md border border-border bg-muted/20 p-3 md:grid-cols-6">
            <label className="flex flex-col gap-1 text-xs md:col-span-2">
                <span className="text-muted-foreground">Person</span>
                <select
                    value={form.user_id}
                    onChange={(e) =>
                        setForm({ ...form, user_id: Number(e.target.value) })
                    }
                    data-tour-id="booking-staff-person"
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                >
                    {candidates.map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name} ({c.email})
                        </option>
                    ))}
                </select>
            </label>
            <label className="flex flex-col gap-1 text-xs md:col-span-2">
                <span className="text-muted-foreground">Role</span>
                <input
                    type="text"
                    value={form.role}
                    onChange={(e) => setForm({ ...form, role: e.target.value })}
                    data-tour-id="booking-staff-role"
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                />
            </label>
            <label className="flex flex-col gap-1 text-xs">
                <span className="text-muted-foreground">Rate ($/hr)</span>
                <input
                    type="number"
                    min={0}
                    step={0.5}
                    value={form.hourly_rate_cents / 100}
                    onChange={(e) =>
                        setForm({
                            ...form,
                            hourly_rate_cents: Math.round(
                                Number(e.target.value) * 100,
                            ),
                        })
                    }
                    data-tour-id="booking-staff-rate"
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                />
            </label>
            <label className="flex flex-col gap-1 text-xs">
                <span className="text-muted-foreground">Start</span>
                <input
                    type="datetime-local"
                    value={form.start_at}
                    onChange={(e) =>
                        setForm({ ...form, start_at: e.target.value })
                    }
                    data-tour-id="booking-staff-shift"
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                />
            </label>
            <label className="flex flex-col gap-1 text-xs">
                <span className="text-muted-foreground">End</span>
                <input
                    type="datetime-local"
                    value={form.end_at}
                    onChange={(e) =>
                        setForm({ ...form, end_at: e.target.value })
                    }
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                />
            </label>
            <label className="flex flex-col gap-1 text-xs md:col-span-5">
                <span className="text-muted-foreground">Notes (optional)</span>
                <input
                    type="text"
                    value={form.notes}
                    onChange={(e) =>
                        setForm({ ...form, notes: e.target.value })
                    }
                    placeholder="Split shift, OT, etc."
                    className="rounded-md border border-border bg-background px-2 py-1.5 text-sm"
                />
            </label>
            <div className="flex items-end justify-end">
                <Button type="button" size="sm" onClick={submit}>
                    Add
                </Button>
            </div>
        </div>
    );
}

BookingsShow.layout = {
    breadcrumbs: [{ title: 'Bookings', href: '/bookings' }],
};
