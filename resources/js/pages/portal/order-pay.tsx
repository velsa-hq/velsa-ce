import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Lock } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Order = {
    id: number;
    order_number: string;
    subtotal_cents: number;
    tax_cents: number;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
};

type Exhibitor = {
    company_name: string;
    email: string;
};

type Props = { order: Order; exhibitor: Exhibitor };

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

// stand-in for the hosted-iframe tokenization; prod never sees the raw PAN
function fakeTokenize(cardNumber: string, brand: string): string {
    const digits = cardNumber.replace(/\D/g, '');
    const last4 = digits.slice(-4) || '0000';

    return `${brand}_tok_${Date.now()}_${last4}`;
}

function guessBrand(cardNumber: string): string {
    const digits = cardNumber.replace(/\D/g, '');

    if (/^4/.test(digits)) {
        return 'visa';
    }

    if (/^5[1-5]/.test(digits)) {
        return 'mc';
    }

    if (/^3[47]/.test(digits)) {
        return 'amex';
    }

    if (/^6/.test(digits)) {
        return 'discover';
    }

    return 'visa';
}

export default function PortalOrderPay({ order, exhibitor }: Props) {
    const [cardNumber, setCardNumber] = useState('');
    const [expiry, setExpiry] = useState('');
    const [cvv, setCvv] = useState('');
    const [name, setName] = useState(exhibitor.company_name);
    const [amountDollars, setAmountDollars] = useState(
        (order.balance_cents / 100).toFixed(2),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        const digits = cardNumber.replace(/\D/g, '');

        if (digits.length < 12) {
            setError('Please enter a valid card number.');

            return;
        }

        const brand = guessBrand(cardNumber);
        const cardToken = fakeTokenize(cardNumber, brand);
        const amountCents = Math.round(parseFloat(amountDollars) * 100);

        if (amountCents <= 0) {
            setError('Amount must be greater than zero.');

            return;
        }

        setProcessing(true);
        router.post(
            `/portal/orders/${order.id}/pay`,
            { card_token: cardToken, amount_cents: amountCents },
            {
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={`Pay order ${order.order_number}`} />

            <div className="mx-auto flex max-w-2xl flex-col gap-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Pay order{' '}
                        <span className="font-mono">{order.order_number}</span>
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Secure payment processed by BluePay.
                    </p>
                </header>

                <section className="rounded-xl border border-border bg-card p-4">
                    <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Balance
                    </h2>
                    <dl className="grid gap-1 text-sm">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Subtotal</dt>
                            <dd className="tabular-nums">
                                {money(order.subtotal_cents)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Tax</dt>
                            <dd className="tabular-nums">
                                {money(order.tax_cents)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Paid</dt>
                            <dd className="text-emerald-700 tabular-nums dark:text-emerald-300">
                                {money(order.paid_cents)}
                            </dd>
                        </div>
                        <div className="flex justify-between border-t border-border pt-1 font-semibold">
                            <dt>Balance due</dt>
                            <dd className="tabular-nums">
                                {money(order.balance_cents)}
                            </dd>
                        </div>
                    </dl>
                </section>

                {/* prod replaces this with the hosted card iframe */}
                <form
                    onSubmit={submit}
                    className="rounded-xl border-2 border-primary/20 bg-card p-4"
                    aria-label="Card payment form"
                >
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            <CreditCard className="size-4" />
                            Card details
                        </h2>
                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Lock className="size-3" />
                            BluePay sandbox
                        </span>
                    </div>

                    <div className="grid gap-3">
                        <div className="grid gap-1">
                            <Label htmlFor="name">Name on card</Label>
                            <Input
                                id="name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                required
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="card">Card number</Label>
                            <Input
                                id="card"
                                inputMode="numeric"
                                placeholder="4242 4242 4242 4242"
                                value={cardNumber}
                                onChange={(e) => setCardNumber(e.target.value)}
                                required
                            />
                            <span className="text-xs text-muted-foreground">
                                Test card 4242...4242 always approves; any
                                number ending in 0000 will be declined.
                            </span>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-1">
                                <Label htmlFor="exp">Expiry</Label>
                                <Input
                                    id="exp"
                                    placeholder="MM / YY"
                                    value={expiry}
                                    onChange={(e) => setExpiry(e.target.value)}
                                    required
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="cvv">CVV</Label>
                                <Input
                                    id="cvv"
                                    inputMode="numeric"
                                    placeholder="123"
                                    value={cvv}
                                    onChange={(e) => setCvv(e.target.value)}
                                    required
                                />
                            </div>
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="amount">Amount to charge ($)</Label>
                            <Input
                                id="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={(order.balance_cents / 100).toFixed(2)}
                                value={amountDollars}
                                onChange={(e) =>
                                    setAmountDollars(e.target.value)
                                }
                                required
                            />
                            <span className="text-xs text-muted-foreground">
                                Partial payments are allowed up to the balance
                                due.
                            </span>
                        </div>

                        {error && (
                            <div
                                role="alert"
                                className="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                            >
                                {error}
                            </div>
                        )}

                        <div className="mt-2 flex items-center gap-3">
                            <Button
                                type="submit"
                                disabled={processing}
                                data-tour-id="portal-pay"
                                className="flex-1"
                            >
                                {processing && <Spinner />}
                                Pay{' '}
                                {money(
                                    Math.round(
                                        parseFloat(amountDollars || '0') * 100,
                                    ),
                                )}
                            </Button>
                            <Link
                                href={`/portal/orders/${order.id}`}
                                className="text-sm text-muted-foreground hover:text-foreground"
                            >
                                Cancel
                            </Link>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}
