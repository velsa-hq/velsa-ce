import { Head, Link, router } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Payment = {
    id: number;
    provider: string | null;
    amount_cents: number;
    refunded_amount_cents: number;
    refundable_cents: number;
    is_fully_refunded: boolean;
    status: string | null;
    card_brand: string | null;
    last4: string | null;
    captured_at: string | null;
    refunded_at: string | null;
};

type InvoiceLine = {
    id: number;
    description: string;
    detail: string | null;
    quantity: number;
    unit_price_cents: number;
    line_total_cents: number;
    reference: string | null;
};

type Invoice = {
    id: number;
    number: string;
    status: string | null;
    status_label: string | null;
    dunning_stage: string | null;
    dunning_label: string | null;
    source: string;
    source_link: { label: string; url: string } | null;
    source_kind: 'exhibitor_order' | 'booking' | 'other';
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    issued_on: string | null;
    due_on: string | null;
    sent_at: string | null;
    paid_at: string | null;
    voided_at: string | null;
    void_reason: string | null;
    net_days: number;
    notes: string | null;
    customer_reference: string | null;
    internal_reference: string | null;
    lines: InvoiceLine[];
    issued_by: string | null;
    days_past_due: number;
    payments: Payment[];
};

type Props = { invoice: Invoice };

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    draft: 'secondary',
    issued: 'default',
    partial_paid: 'default',
    paid: 'outline',
    past_due: 'destructive',
    void: 'secondary',
    written_off: 'destructive',
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function InvoiceShow({ invoice }: Props) {
    const [reason, setReason] = useState('');
    const [voiding, setVoiding] = useState(false);
    const [writeOffReason, setWriteOffReason] = useState('');
    const [writingOff, setWritingOff] = useState(false);

    const canVoid =
        invoice.status !== 'paid' &&
        invoice.status !== 'void' &&
        invoice.status !== 'written_off';

    const canWriteOff =
        invoice.balance_cents > 0 &&
        invoice.status !== 'draft' &&
        invoice.status !== 'paid' &&
        invoice.status !== 'void' &&
        invoice.status !== 'written_off';

    const submitVoid = (e: React.FormEvent) => {
        e.preventDefault();

        if (!reason.trim() || !confirm('Void this invoice?')) {
            return;
        }

        router.post(
            `/admin/invoices/${invoice.number}/void`,
            { reason },
            {
                onStart: () => setVoiding(true),
                onFinish: () => setVoiding(false),
            },
        );
    };

    const submitWriteOff = (e: React.FormEvent) => {
        e.preventDefault();

        if (
            !writeOffReason.trim() ||
            !confirm(
                `Write off the ${money(invoice.balance_cents)} balance as bad debt? This is terminal and posts a bad-debt journal entry.`,
            )
        ) {
            return;
        }

        router.post(
            `/admin/invoices/${invoice.number}/write-off`,
            { reason: writeOffReason },
            {
                onStart: () => setWritingOff(true),
                onFinish: () => setWritingOff(false),
            },
        );
    };

    return (
        <>
            <Head title={`Invoice ${invoice.number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Invoice{' '}
                            <span className="font-mono">{invoice.number}</span>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {invoice.source}
                            {invoice.source_link && (
                                <>
                                    {' · '}
                                    <Link
                                        href={invoice.source_link.url}
                                        className="hover:underline"
                                    >
                                        {invoice.source_link.label}
                                    </Link>
                                </>
                            )}
                        </p>
                    </div>
                    <div className="flex items-start gap-3">
                        <div className="flex flex-col items-end gap-1">
                            {invoice.status && (
                                <Badge
                                    variant={
                                        STATUS_VARIANTS[invoice.status] ??
                                        'secondary'
                                    }
                                >
                                    {invoice.status_label}
                                </Badge>
                            )}
                            {invoice.dunning_stage &&
                                invoice.dunning_stage !== 'none' && (
                                    <Badge variant="outline">
                                        {invoice.dunning_label}
                                    </Badge>
                                )}
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={`/admin/invoices/${invoice.number}/pdf`}
                                target="_blank"
                                rel="noopener"
                            >
                                <FileDown className="size-4" />
                                Download PDF
                            </a>
                        </Button>
                    </div>
                </header>

                <LineItemsCard lines={invoice.lines} />
                <ReferencesCard
                    invoiceNumber={invoice.number}
                    customer={invoice.customer_reference}
                    internal={invoice.internal_reference}
                />

                <div className="grid gap-4 lg:grid-cols-[2fr_1fr]">
                    <section className="rounded-xl border border-border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Totals
                        </h2>
                        <dl className="grid gap-1 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">
                                    Subtotal
                                </dt>
                                <dd className="tabular-nums">
                                    {money(invoice.subtotal_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Tax</dt>
                                <dd className="tabular-nums">
                                    {money(invoice.tax_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                <dt>Total</dt>
                                <dd className="tabular-nums">
                                    {money(invoice.total_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Paid</dt>
                                <dd className="text-emerald-700 tabular-nums dark:text-emerald-300">
                                    {money(invoice.paid_cents)}
                                </dd>
                            </div>
                            <div className="flex justify-between border-t border-border pt-1 font-semibold">
                                <dt>Balance due</dt>
                                <dd className="tabular-nums">
                                    {money(invoice.balance_cents)}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section className="rounded-xl border border-border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Lifecycle
                        </h2>
                        <dl className="grid gap-2 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Issued
                                </dt>
                                <dd>
                                    {invoice.issued_on ?? '-'}
                                    {invoice.issued_by && (
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            by {invoice.issued_by}
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Due
                                </dt>
                                <dd>
                                    {invoice.due_on ?? '-'}
                                    {invoice.days_past_due > 0 && (
                                        <span className="ml-1 text-xs text-amber-700 dark:text-amber-300">
                                            ({invoice.days_past_due} days past
                                            due)
                                        </span>
                                    )}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Sent
                                </dt>
                                <dd>{formatDate(invoice.sent_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Paid in full
                                </dt>
                                <dd>{formatDate(invoice.paid_at)}</dd>
                            </div>
                            {invoice.voided_at && (
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Voided
                                    </dt>
                                    <dd>
                                        {formatDate(invoice.voided_at)}
                                        {invoice.void_reason && (
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                {invoice.void_reason}
                                            </div>
                                        )}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>
                </div>

                <section className="rounded-xl border border-border bg-card p-4">
                    <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Payments ({invoice.payments.length})
                    </h2>
                    {invoice.payments.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No payments recorded yet.
                        </p>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {invoice.payments.map((p) => (
                                <PaymentRow
                                    key={p.id}
                                    invoice={invoice}
                                    payment={p}
                                />
                            ))}
                        </ul>
                    )}
                </section>

                {invoice.balance_cents > 0 && (
                    <ManualPaymentForm invoice={invoice} />
                )}

                {invoice.source_kind !== 'exhibitor_order' &&
                    invoice.paid_cents > 0 && (
                        <InvoiceRefundForm invoice={invoice} />
                    )}

                {canVoid && (
                    <section
                        data-tour-id="inv-void"
                        className="rounded-xl border border-destructive/30 bg-destructive/5 p-4"
                    >
                        <h2 className="mb-2 text-sm font-semibold tracking-wider text-destructive uppercase">
                            Void invoice
                        </h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Use this when the invoice was issued in error.
                            Voiding does not refund any payments already
                            captured against it.
                        </p>
                        <form
                            onSubmit={submitVoid}
                            className="flex flex-col gap-2 sm:flex-row sm:items-end"
                        >
                            <input
                                type="text"
                                placeholder="Reason (required)"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                                required
                                className="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm"
                            />
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={voiding || !reason.trim()}
                            >
                                Void invoice
                            </Button>
                        </form>
                    </section>
                )}

                {canWriteOff && (
                    <section
                        data-tour-id="inv-write-off"
                        className="rounded-xl border border-destructive/30 bg-destructive/5 p-4"
                    >
                        <h2 className="mb-2 text-sm font-semibold tracking-wider text-destructive uppercase">
                            Write off balance
                        </h2>
                        <p className="mb-3 text-sm text-muted-foreground">
                            Use this when the{' '}
                            <strong>{money(invoice.balance_cents)}</strong>{' '}
                            outstanding is uncollectable. This is terminal and
                            posts a bad-debt journal entry (debit Bad Debt
                            Expense, credit A/R).
                        </p>
                        <form
                            onSubmit={submitWriteOff}
                            className="flex flex-col gap-2 sm:flex-row sm:items-end"
                        >
                            <input
                                type="text"
                                placeholder="Reason (required)"
                                value={writeOffReason}
                                onChange={(e) =>
                                    setWriteOffReason(e.target.value)
                                }
                                required
                                className="flex-1 rounded-md border border-border bg-background px-3 py-2 text-sm"
                            />
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={writingOff || !writeOffReason.trim()}
                            >
                                Write off
                            </Button>
                        </form>
                    </section>
                )}
            </div>
        </>
    );
}

function PaymentRow({
    invoice,
    payment,
}: {
    invoice: Invoice;
    payment: Payment;
}) {
    const [showForm, setShowForm] = useState(false);
    const [amount, setAmount] = useState(
        (payment.refundable_cents / 100).toFixed(2),
    );
    const [reason, setReason] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const cents = Math.round(parseFloat(amount || '0') * 100);

        if (cents <= 0) {
            return;
        }

        if (!confirm(`Refund ${money(cents)} on payment #${payment.id}?`)) {
            return;
        }

        router.post(
            `/admin/invoices/${invoice.number}/payments/${payment.id}/refund`,
            { amount_cents: cents, reason: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setShowForm(false);
                    setReason('');
                },
            },
        );
    };

    const refundable = payment.refundable_cents > 0;
    const partial =
        payment.refunded_amount_cents > 0 && !payment.is_fully_refunded;

    return (
        <li className="rounded-lg border border-border/60 bg-background p-3 text-sm">
            <div className="flex items-start justify-between gap-3">
                <div className="flex flex-col">
                    <span>
                        {payment.card_brand ?? 'card'}{' '}
                        {payment.last4 ? `••${payment.last4}` : ''}
                        {payment.provider === 'manual' && (
                            <Badge variant="outline" className="ml-2 text-xs">
                                manual
                            </Badge>
                        )}
                        {payment.is_fully_refunded && (
                            <Badge
                                variant="destructive"
                                className="ml-2 text-xs"
                            >
                                refunded
                            </Badge>
                        )}
                        {partial && (
                            <Badge variant="secondary" className="ml-2 text-xs">
                                partial refund
                            </Badge>
                        )}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        Captured {formatDate(payment.captured_at)}
                        {payment.refunded_at && (
                            <>
                                {' · '}refunded{' '}
                                {formatDate(payment.refunded_at)}
                            </>
                        )}
                    </span>
                </div>
                <div className="flex items-center gap-3">
                    <div className="text-right">
                        <div className="tabular-nums">
                            {money(payment.amount_cents)}
                        </div>
                        {payment.refunded_amount_cents > 0 && (
                            <div className="text-xs text-amber-700 tabular-nums dark:text-amber-300">
                                -{money(payment.refunded_amount_cents)} refunded
                            </div>
                        )}
                    </div>
                    {refundable && !showForm && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setShowForm(true)}
                        >
                            Refund
                        </Button>
                    )}
                </div>
            </div>

            {showForm && (
                <form
                    onSubmit={submit}
                    className="mt-3 grid gap-2 border-t border-border/40 pt-3 sm:grid-cols-[1fr_2fr_auto]"
                >
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={(payment.refundable_cents / 100).toFixed(2)}
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                        required
                        placeholder="Amount ($)"
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                    <input
                        type="text"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Reason (optional)"
                        maxLength={500}
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                    <div className="flex items-center gap-2">
                        <Button type="submit" size="sm" variant="destructive">
                            Refund
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setShowForm(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            )}
        </li>
    );
}

function ManualPaymentForm({ invoice }: { invoice: Invoice }) {
    const [method, setMethod] = useState<'check' | 'wire' | 'cash' | 'ach'>(
        'check',
    );
    const [amountDollars, setAmountDollars] = useState(
        (invoice.balance_cents / 100).toFixed(2),
    );
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const cents = Math.round(parseFloat(amountDollars || '0') * 100);

        if (cents <= 0) {
            return;
        }

        router.post(
            `/admin/invoices/${invoice.number}/payments`,
            {
                amount_cents: cents,
                method,
                reference: reference || null,
                note: note || null,
            },
            { preserveScroll: true },
        );
    };

    return (
        <section
            data-tour-id="inv-record-payment"
            className="rounded-xl border border-border bg-card p-4"
        >
            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                Record manual payment
            </h2>
            <p className="mb-3 text-sm text-muted-foreground">
                Use this for payments received outside BluePay - checks, wires,
                cash, ACH. Fires the same journal-entry + receipt pipeline as a
                card capture.
            </p>
            <form
                onSubmit={submit}
                className="grid gap-3 sm:grid-cols-2"
                aria-label="Manual payment form"
            >
                <div className="grid gap-1">
                    <label className="text-sm font-medium">Method</label>
                    <select
                        value={method}
                        onChange={(e) =>
                            setMethod(
                                e.target.value as
                                    | 'check'
                                    | 'wire'
                                    | 'cash'
                                    | 'ach',
                            )
                        }
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    >
                        <option value="check">Check</option>
                        <option value="wire">Wire transfer</option>
                        <option value="cash">Cash</option>
                        <option value="ach">ACH</option>
                    </select>
                </div>
                <div className="grid gap-1">
                    <label className="text-sm font-medium">Amount ($)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={(invoice.balance_cents / 100).toFixed(2)}
                        value={amountDollars}
                        onChange={(e) => setAmountDollars(e.target.value)}
                        required
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div className="grid gap-1">
                    <label className="text-sm font-medium">
                        Reference{' '}
                        <span className="text-muted-foreground">
                            (optional)
                        </span>
                    </label>
                    <input
                        type="text"
                        value={reference}
                        onChange={(e) => setReference(e.target.value)}
                        placeholder="Check #4502, wire conf #XYZ..."
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div className="grid gap-1">
                    <label className="text-sm font-medium">
                        Note{' '}
                        <span className="text-muted-foreground">
                            (optional)
                        </span>
                    </label>
                    <input
                        type="text"
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        placeholder="Received in person, dated..."
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div className="sm:col-span-2">
                    <Button type="submit">Record payment</Button>
                </div>
            </form>
        </section>
    );
}

function InvoiceRefundForm({ invoice }: { invoice: Invoice }) {
    const [amountDollars, setAmountDollars] = useState(
        (invoice.paid_cents / 100).toFixed(2),
    );
    const [reason, setReason] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const cents = Math.round(parseFloat(amountDollars || '0') * 100);

        if (cents <= 0) {
            return;
        }

        if (!confirm(`Refund ${money(cents)} on invoice ${invoice.number}?`)) {
            return;
        }

        router.post(
            `/admin/invoices/${invoice.number}/refund`,
            { amount_cents: cents, reason: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReason('');
                },
            },
        );
    };

    return (
        <section className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4">
            <h2 className="mb-2 text-sm font-semibold tracking-wider text-amber-700 uppercase dark:text-amber-400">
                Refund invoice
            </h2>
            <p className="mb-3 text-sm text-muted-foreground">
                Walks the invoice's paid amount back and posts a reversing
                journal pair (debit AR, credit Cash). Finance is responsible for
                cutting the refund check / initiating the wire reversal
                externally.
            </p>
            <form
                onSubmit={submit}
                className="grid gap-3 sm:grid-cols-2"
                aria-label="Invoice refund form"
            >
                <div className="grid gap-1">
                    <label className="text-sm font-medium">Amount ($)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        max={(invoice.paid_cents / 100).toFixed(2)}
                        value={amountDollars}
                        onChange={(e) => setAmountDollars(e.target.value)}
                        required
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div className="grid gap-1">
                    <label className="text-sm font-medium">
                        Reason{' '}
                        <span className="text-muted-foreground">
                            (optional)
                        </span>
                    </label>
                    <input
                        type="text"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Booking cancelled, deposit returned..."
                        maxLength={500}
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div className="sm:col-span-2">
                    <Button type="submit" variant="outline">
                        Refund invoice
                    </Button>
                </div>
            </form>
        </section>
    );
}

function LineItemsCard({ lines }: { lines: InvoiceLine[] }) {
    if (lines.length === 0) {
        return null;
    }

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                Line items
            </h2>
            <table className="w-full text-sm">
                <thead className="text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                    <tr className="border-b border-border">
                        <th className="py-1.5">Description</th>
                        <th className="py-1.5 text-right">Qty</th>
                        <th className="py-1.5 text-right">Unit</th>
                        <th className="py-1.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((l) => (
                        <tr
                            key={l.id}
                            className="border-b border-border last:border-0"
                        >
                            <td className="py-1.5">
                                <div className="font-medium">
                                    {l.description}
                                </div>
                                {l.detail && (
                                    <div className="text-xs text-muted-foreground">
                                        {l.detail}
                                    </div>
                                )}
                                {l.reference && (
                                    <div className="text-xs text-muted-foreground">
                                        Ref: {l.reference}
                                    </div>
                                )}
                            </td>
                            <td className="py-1.5 text-right tabular-nums">
                                {l.quantity}
                            </td>
                            <td className="py-1.5 text-right tabular-nums">
                                {money(l.unit_price_cents)}
                            </td>
                            <td className="py-1.5 text-right font-medium tabular-nums">
                                {money(l.line_total_cents)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </section>
    );
}

function ReferencesCard({
    invoiceNumber,
    customer,
    internal,
}: {
    invoiceNumber: string;
    customer: string | null;
    internal: string | null;
}) {
    const [customerValue, setCustomerValue] = useState(customer ?? '');
    const [internalValue, setInternalValue] = useState(internal ?? '');
    const dirty =
        customerValue !== (customer ?? '') ||
        internalValue !== (internal ?? '');

    const save = (e: React.FormEvent) => {
        e.preventDefault();
        router.patch(
            `/admin/invoices/${invoiceNumber}/references`,
            {
                customer_reference: customerValue || null,
                internal_reference: internalValue || null,
            },
            { preserveScroll: true },
        );
    };

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                References
            </h2>
            <form onSubmit={save} className="grid gap-3 sm:grid-cols-2">
                <label className="flex flex-col gap-1 text-xs font-medium">
                    Customer reference (their PO)
                    <input
                        type="text"
                        value={customerValue}
                        onChange={(e) => setCustomerValue(e.target.value)}
                        placeholder="e.g. PO-12345"
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </label>
                <label className="flex flex-col gap-1 text-xs font-medium">
                    Internal reference (project / event code)
                    <input
                        type="text"
                        value={internalValue}
                        onChange={(e) => setInternalValue(e.target.value)}
                        placeholder="e.g. PROJ-2026-042"
                        className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                    />
                </label>
                <div className="flex justify-end sm:col-span-2">
                    <Button type="submit" size="sm" disabled={!dirty}>
                        Save references
                    </Button>
                </div>
            </form>
        </section>
    );
}

InvoiceShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/invoices' },
        { title: 'Invoices', href: '/admin/invoices' },
        { title: 'Detail', href: '#' },
    ],
};
