import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const selectClass =
    'w-full min-w-0 rounded-md border border-input bg-background px-3 py-2 text-sm';

export type Payment = {
    id: number;
    provider: string | null;
    status: string | null;
    amount_cents: number;
    refunded_amount_cents: number;
    refundable_cents: number;
    card_brand: string | null;
    last4: string | null;
    processed_at: string | null;
    refunded_at: string | null;
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function centsFromDollars(value: string): number | null {
    const n = Number(value);

    if (!Number.isFinite(n) || n <= 0) {
        return null;
    }

    return Math.round(n * 100);
}

export default function OrderPayments({
    exhibitorId,
    orderId,
    balanceCents,
    payments,
}: {
    exhibitorId: number;
    orderId: number;
    balanceCents: number;
    payments: Payment[];
}) {
    const [taking, setTaking] = useState(false);
    const [refundFor, setRefundFor] = useState<Payment | null>(null);
    const base = `/exhibitors/${exhibitorId}/orders/${orderId}`;

    return (
        <section className="rounded-xl border border-border bg-card p-4">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                    Payments ({payments.length})
                </h2>
                <Button
                    size="sm"
                    onClick={() => setTaking(true)}
                    disabled={balanceCents <= 0}
                    data-tour-id="ex-take-payment"
                >
                    Record payment
                </Button>
            </div>

            {payments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No payments yet.
                </p>
            ) : (
                <ul className="flex flex-col gap-2 text-sm">
                    {payments.map((p) => (
                        <li
                            key={p.id}
                            className="flex items-start justify-between gap-2 border-b border-border/40 pb-2 last:border-0"
                        >
                            <div className="flex flex-col">
                                <span>
                                    {p.provider === 'manual'
                                        ? (p.card_brand ?? 'manual')
                                        : `${p.card_brand ?? 'card'} ${p.last4 ? `••${p.last4}` : ''}`}
                                    {p.status && p.status !== 'captured' ? (
                                        <Badge
                                            variant="secondary"
                                            className="ml-2"
                                        >
                                            {p.status}
                                        </Badge>
                                    ) : null}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {p.processed_at
                                        ? new Date(
                                              p.processed_at,
                                          ).toLocaleDateString()
                                        : ''}
                                    {p.refunded_amount_cents > 0
                                        ? ` · refunded ${money(p.refunded_amount_cents)}`
                                        : ''}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="tabular-nums">
                                    {money(p.amount_cents)}
                                </span>
                                {p.refundable_cents > 0 ? (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-rose-700 hover:text-rose-800 dark:text-rose-300"
                                        onClick={() => setRefundFor(p)}
                                    >
                                        Refund
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <TakePaymentModal
                open={taking}
                onClose={() => setTaking(false)}
                base={base}
                balanceCents={balanceCents}
            />
            <RefundModal
                payment={refundFor}
                onClose={() => setRefundFor(null)}
                base={base}
            />
        </section>
    );
}

function TakePaymentModal({
    open,
    onClose,
    base,
    balanceCents,
}: {
    open: boolean;
    onClose: () => void;
    base: string;
    balanceCents: number;
}) {
    const [mode, setMode] = useState<'card' | 'manual'>('card');
    const [cardToken, setCardToken] = useState('');
    const [amount, setAmount] = useState(String(balanceCents / 100));
    const [method, setMethod] = useState('check');
    const [reference, setReference] = useState('');
    const [note, setNote] = useState('');
    const [saving, setSaving] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const cents = centsFromDollars(amount);

        if (cents === null) {
            return;
        }

        setSaving(true);
        const opts = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setSaving(false),
        };

        if (mode === 'card') {
            router.post(
                `${base}/payments`,
                { card_token: cardToken, amount_cents: cents },
                opts,
            );
        } else {
            router.post(
                `${base}/payments/manual`,
                {
                    amount_cents: cents,
                    method,
                    reference: reference || null,
                    note: note || null,
                },
                opts,
            );
        }
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                {open ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Record payment</DialogTitle>
                        </DialogHeader>

                        <div className="grid gap-1.5">
                            <Label htmlFor="pay-mode">Type</Label>
                            <select
                                id="pay-mode"
                                value={mode}
                                onChange={(e) =>
                                    setMode(e.target.value as 'card' | 'manual')
                                }
                                className={selectClass}
                            >
                                <option value="card">Card (BluePay)</option>
                                <option value="manual">
                                    Manual (check / wire / cash / ACH)
                                </option>
                            </select>
                        </div>

                        {mode === 'card' ? (
                            <div className="grid gap-1.5">
                                <Label htmlFor="pay-token">Card token</Label>
                                <Input
                                    id="pay-token"
                                    value={cardToken}
                                    onChange={(e) =>
                                        setCardToken(e.target.value)
                                    }
                                    placeholder="from BluePay hosted field"
                                    required
                                />
                                <p className="text-xs text-muted-foreground">
                                    In production this comes from the BluePay
                                    hosted card field; the dev driver accepts
                                    any token whose last four are non-zero
                                    digits.
                                </p>
                            </div>
                        ) : (
                            <>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="pay-method">Method</Label>
                                    <select
                                        id="pay-method"
                                        value={method}
                                        onChange={(e) =>
                                            setMethod(e.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        <option value="check">Check</option>
                                        <option value="wire">Wire</option>
                                        <option value="cash">Cash</option>
                                        <option value="ach">ACH</option>
                                    </select>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="pay-ref">
                                        Reference (optional)
                                    </Label>
                                    <Input
                                        id="pay-ref"
                                        value={reference}
                                        onChange={(e) =>
                                            setReference(e.target.value)
                                        }
                                        placeholder="check # / wire ref"
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="pay-note">
                                        Note (optional)
                                    </Label>
                                    <Input
                                        id="pay-note"
                                        value={note}
                                        onChange={(e) =>
                                            setNote(e.target.value)
                                        }
                                    />
                                </div>
                            </>
                        )}

                        <div className="grid gap-1.5">
                            <Label htmlFor="pay-amount">Amount ($)</Label>
                            <Input
                                id="pay-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                                required
                            />
                            <p className="text-xs text-muted-foreground">
                                Balance due: {money(balanceCents)}
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saving}>
                                Record
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function RefundModal({
    payment,
    onClose,
    base,
}: {
    payment: Payment | null;
    onClose: () => void;
    base: string;
}) {
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [saving, setSaving] = useState(false);
    const open = payment !== null;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!payment) {
            return;
        }

        const cents = centsFromDollars(
            amount || String(payment.refundable_cents / 100),
        );

        if (cents === null) {
            return;
        }

        setSaving(true);
        router.post(
            `${base}/payments/${payment.id}/refund`,
            { amount_cents: cents, reason: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAmount('');
                    setReason('');
                    onClose();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                {payment ? (
                    <form onSubmit={submit} className="flex flex-col gap-4">
                        <DialogHeader>
                            <DialogTitle>Refund payment</DialogTitle>
                        </DialogHeader>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ref-amount">Amount ($)</Label>
                            <Input
                                id="ref-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value)}
                                placeholder={String(
                                    payment.refundable_cents / 100,
                                )}
                            />
                            <p className="text-xs text-muted-foreground">
                                Refundable: {money(payment.refundable_cents)}
                            </p>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="ref-reason">
                                Reason (optional)
                            </Label>
                            <Input
                                id="ref-reason"
                                value={reason}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={saving}>
                                Refund
                            </Button>
                        </DialogFooter>
                    </form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
