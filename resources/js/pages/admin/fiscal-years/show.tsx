import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Year = {
    id: number;
    label: string;
    starts_on: string | null;
    ends_on: string | null;
    is_closed: boolean;
    closed_at: string | null;
};

type SummaryRow = {
    budget_id: number | null;
    account_id: number;
    account_code: string;
    account_name: string;
    account_type: string | null;
    fund_id: number | null;
    fund_code: string | null;
    fund_name: string | null;
    budgeted_cents: number;
    actual_cents: number;
    variance_cents: number;
    used_pct: number | null;
    is_unbudgeted: boolean;
};

type AccountOption = {
    id: number;
    code: string;
    name: string;
    account_type: string | null;
};

type FundOption = { id: number; code: string; name: string };

type Totals = Record<
    string,
    { budgeted_cents: number; actual_cents: number; variance_cents: number }
>;

type Props = {
    year: Year;
    summary: SummaryRow[];
    totals_by_type: Totals;
    accounts: AccountOption[];
    funds: FundOption[];
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

const TYPE_ORDER = ['revenue', 'expense', 'asset', 'liability', 'equity'];

export default function FiscalYearShow({
    year,
    summary,
    totals_by_type,
    accounts,
    funds,
}: Props) {
    const [accountId, setAccountId] = useState<number | ''>('');
    const [fundId, setFundId] = useState<number | ''>('');
    const [amount, setAmount] = useState('');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editAmount, setEditAmount] = useState('');

    const grouped = useMemo(() => {
        const out: Record<string, SummaryRow[]> = {};

        for (const r of summary) {
            const t = r.account_type ?? 'other';
            (out[t] ??= []).push(r);
        }

        return TYPE_ORDER.map((t) => ({ type: t, rows: out[t] ?? [] })).filter(
            (g) => g.rows.length > 0,
        );
    }, [summary]);

    const setBudget = (e: React.FormEvent) => {
        e.preventDefault();

        if (accountId === '' || amount === '') {
            return;
        }

        const amountCents = Math.round(parseFloat(amount) * 100);
        router.post(
            `/admin/fiscal-years/${year.label}/budgets`,
            {
                chart_of_account_id: accountId,
                fund_id: fundId === '' ? null : fundId,
                amount_cents: amountCents,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAccountId('');
                    setFundId('');
                    setAmount('');
                },
            },
        );
    };

    const startEdit = (r: SummaryRow) => {
        setEditingId(r.budget_id);
        setEditAmount((r.budgeted_cents / 100).toFixed(2));
    };

    const saveEdit = (r: SummaryRow) => {
        if (editAmount === '') {
            return;
        }

        router.post(
            `/admin/fiscal-years/${year.label}/budgets`,
            {
                chart_of_account_id: r.account_id,
                fund_id: r.fund_id,
                amount_cents: Math.round(parseFloat(editAmount) * 100),
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditingId(null),
            },
        );
    };

    const deleteBudget = (r: SummaryRow) => {
        if (
            r.budget_id === null ||
            !window.confirm(
                `Delete the ${r.account_code} ${r.account_name} budget line? This can't be undone.`,
            )
        ) {
            return;
        }

        router.delete(
            `/admin/fiscal-years/${year.label}/budgets/${r.budget_id}`,
            { preserveScroll: true },
        );
    };

    const toggleClose = () => {
        const path = year.is_closed ? 'reopen' : 'close';
        router.post(
            `/admin/fiscal-years/${year.label}/${path}`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <>
            <Head title={`${year.label} · Budget`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {year.label} budget
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {year.starts_on} - {year.ends_on}{' '}
                            {year.is_closed ? (
                                <Badge
                                    variant="secondary"
                                    className="ml-2"
                                    data-tour-id="budget-year-status"
                                >
                                    Closed
                                </Badge>
                            ) : (
                                <Badge
                                    variant="outline"
                                    className="ml-2"
                                    data-tour-id="budget-year-status"
                                >
                                    Open
                                </Badge>
                            )}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/admin/fiscal-years">Back</Link>
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            data-tour-id="budget-variance-report"
                        >
                            <Link
                                href={`/reports/budget-vs-actual?fiscal_year_id=${year.id}`}
                            >
                                Variance report (PDF / XLS)
                            </Link>
                        </Button>
                        <Button
                            variant={year.is_closed ? 'outline' : 'destructive'}
                            size="sm"
                            onClick={toggleClose}
                            data-tour-id="budget-close-year"
                        >
                            {year.is_closed ? 'Reopen' : 'Close year'}
                        </Button>
                    </div>
                </header>

                {/* Type rollups */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {TYPE_ORDER.map((t) => {
                        const tt = totals_by_type[t];

                        if (!tt) {
                            return null;
                        }

                        return (
                            <div
                                key={t}
                                className="rounded-lg border border-border bg-card p-3"
                            >
                                <div className="text-xs tracking-wider text-muted-foreground uppercase">
                                    {t}
                                </div>
                                <div className="mt-1 grid gap-0.5 text-xs">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Budget
                                        </span>
                                        <span className="tabular-nums">
                                            {money(tt.budgeted_cents)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Actual
                                        </span>
                                        <span className="tabular-nums">
                                            {money(tt.actual_cents)}
                                        </span>
                                    </div>
                                    <div
                                        className={`flex justify-between font-semibold ${
                                            tt.variance_cents < 0
                                                ? 'text-amber-700 dark:text-amber-300'
                                                : 'text-emerald-700 dark:text-emerald-300'
                                        }`}
                                    >
                                        <span>Variance</span>
                                        <span className="tabular-nums">
                                            {money(tt.variance_cents)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Budget editor */}
                {!year.is_closed && (
                    <section className="rounded-xl border border-border bg-card p-4">
                        <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                            Set / update budget line
                        </h2>
                        <form
                            onSubmit={setBudget}
                            className="grid gap-3 sm:grid-cols-[2fr_1fr_1fr_auto] sm:items-end"
                            data-tour-id="budget-set-form"
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="account">Account</Label>
                                <select
                                    id="account"
                                    value={accountId}
                                    onChange={(e) =>
                                        setAccountId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                    required
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">Pick account...</option>
                                    {accounts.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.code} · {a.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="fund">Fund (optional)</Label>
                                <select
                                    id="fund"
                                    value={fundId}
                                    onChange={(e) =>
                                        setFundId(
                                            e.target.value
                                                ? Number(e.target.value)
                                                : '',
                                        )
                                    }
                                    className="rounded-md border border-border bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">(none)</option>
                                    {funds.map((f) => (
                                        <option key={f.id} value={f.id}>
                                            {f.code}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="amount">Amount ($)</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    required
                                />
                            </div>
                            <Button type="submit">Save</Button>
                        </form>
                    </section>
                )}

                {/* Budget lines grouped by account type */}
                <div className="flex flex-col gap-4">
                    {grouped.map((g) => (
                        <section key={g.type}>
                            <h2 className="mb-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                {g.type}
                            </h2>
                            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table
                                    className="w-full text-sm"
                                    data-tour-id="budget-line-table"
                                >
                                    <thead className="bg-muted/50">
                                        <tr>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Code
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Account
                                            </th>
                                            <th className="px-3 py-2 text-left font-medium">
                                                Fund
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Budget
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Actual
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Variance
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Used %
                                            </th>
                                            {!year.is_closed && (
                                                <th className="px-3 py-2" />
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {g.rows.map((r) => (
                                            <tr
                                                key={`${r.account_id}-${r.fund_code ?? 'x'}`}
                                                className="border-t border-border/60"
                                            >
                                                <td className="px-3 py-2 font-mono">
                                                    {r.account_code}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {r.account_name}
                                                    {r.is_unbudgeted && (
                                                        <Badge
                                                            variant="destructive"
                                                            className="ml-2"
                                                        >
                                                            unbudgeted
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {r.fund_code ?? '-'}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">
                                                    {editingId !== null &&
                                                    editingId ===
                                                        r.budget_id ? (
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={editAmount}
                                                            onChange={(e) =>
                                                                setEditAmount(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 w-28 text-right"
                                                        />
                                                    ) : (
                                                        money(r.budgeted_cents)
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right tabular-nums">
                                                    {money(r.actual_cents)}
                                                </td>
                                                <td
                                                    className={`px-3 py-2 text-right tabular-nums ${
                                                        r.variance_cents < 0
                                                            ? 'text-amber-700 dark:text-amber-300'
                                                            : 'text-emerald-700 dark:text-emerald-300'
                                                    }`}
                                                >
                                                    {money(r.variance_cents)}
                                                </td>
                                                <td className="px-3 py-2 text-right text-muted-foreground tabular-nums">
                                                    {r.used_pct !== null
                                                        ? `${r.used_pct}%`
                                                        : '-'}
                                                </td>
                                                {!year.is_closed && (
                                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                                        {r.budget_id ===
                                                        null ? null : editingId ===
                                                          r.budget_id ? (
                                                            <span className="flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        saveEdit(
                                                                            r,
                                                                        )
                                                                    }
                                                                    className="text-xs font-medium underline hover:text-foreground"
                                                                >
                                                                    Save
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        setEditingId(
                                                                            null,
                                                                        )
                                                                    }
                                                                    className="text-xs text-muted-foreground underline"
                                                                >
                                                                    Cancel
                                                                </button>
                                                            </span>
                                                        ) : (
                                                            <span className="flex justify-end gap-2">
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        startEdit(
                                                                            r,
                                                                        )
                                                                    }
                                                                    className="text-xs underline hover:text-foreground"
                                                                >
                                                                    Edit
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        deleteBudget(
                                                                            r,
                                                                        )
                                                                    }
                                                                    className="text-xs text-rose-700 underline hover:text-rose-800 dark:text-rose-300"
                                                                >
                                                                    Delete
                                                                </button>
                                                            </span>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ))}
                    {grouped.length === 0 && (
                        <div className="rounded-xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            No budget lines yet. Add one above to start tracking
                            variance.
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

FiscalYearShow.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/fiscal-years' },
        { title: 'Fiscal years', href: '/admin/fiscal-years' },
        { title: 'Budget', href: '#' },
    ],
};
