import { Head, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/admin/sales-goals';

type Goal = {
    id: number;
    user_id: number;
    user_name: string | null;
    year: number;
    month: number | null;
    target_cents: number;
};

type Salesperson = { id: number; name: string };

type Props = {
    goals: Goal[];
    salespeople: Salesperson[];
    current_year: number;
};

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

function usd(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

function periodLabel(g: Goal): string {
    if (g.month === null) {
        return `FY ${g.year}`;
    }

    const month =
        g.month >= 1 && g.month <= 12
            ? MONTHS[g.month - 1]
            : `Month ${g.month}`;

    return `${month} ${g.year}`;
}

export default function SalesGoalsIndex({
    goals,
    salespeople,
    current_year,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: salespeople[0]?.id ?? 0,
        year: current_year,
        month: '' as string,
        target_dollars: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(store().url, {
            preserveScroll: true,
            onSuccess: () => reset('target_dollars'),
        });
    };

    const remove = (g: Goal) => {
        if (
            window.confirm(
                `Remove the ${periodLabel(g)} goal for ${g.user_name}?`,
            )
        ) {
            router.delete(destroy(g.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Sales goals · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Sales goals
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Set per-salesperson revenue targets. Attainment vs.
                        actual booked revenue is in the Sales goal attainment
                        report.
                    </p>
                </header>

                <Card>
                    <CardContent className="p-4">
                        <form
                            onSubmit={submit}
                            className="grid gap-3 sm:grid-cols-5 sm:items-end"
                        >
                            <div className="grid gap-1">
                                <Label htmlFor="user_id">Salesperson</Label>
                                <select
                                    id="user_id"
                                    data-tour-id="sg-salesperson-select"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={data.user_id}
                                    onChange={(e) =>
                                        setData(
                                            'user_id',
                                            Number(e.target.value),
                                        )
                                    }
                                >
                                    {salespeople.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="year">Year</Label>
                                <Input
                                    id="year"
                                    data-tour-id="sg-year-input"
                                    type="number"
                                    value={data.year}
                                    onChange={(e) =>
                                        setData('year', Number(e.target.value))
                                    }
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="month">Period</Label>
                                <select
                                    id="month"
                                    data-tour-id="sg-period-select"
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    value={data.month}
                                    onChange={(e) =>
                                        setData('month', e.target.value)
                                    }
                                >
                                    <option value="">Whole year</option>
                                    {MONTHS.map((m, i) => (
                                        <option key={m} value={i + 1}>
                                            {m}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="target_dollars">Goal ($)</Label>
                                <Input
                                    id="target_dollars"
                                    data-tour-id="sg-goal-amount"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    value={data.target_dollars}
                                    onChange={(e) =>
                                        setData(
                                            'target_dollars',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                data-tour-id="sg-save-goal"
                                disabled={processing}
                            >
                                Save goal
                            </Button>
                        </form>
                        {(errors.user_id ||
                            errors.target_dollars ||
                            errors.year) && (
                            <p className="mt-2 text-xs text-destructive">
                                {errors.user_id ??
                                    errors.target_dollars ??
                                    errors.year}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Salesperson
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Period
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Goal
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {goals.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No goals set yet.
                                    </td>
                                </tr>
                            ) : (
                                goals.map((g) => (
                                    <tr key={g.id}>
                                        <td className="px-4 py-2">
                                            {g.user_name ?? '-'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {periodLabel(g)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {usd(g.target_cents)}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                data-tour-id="sg-goal-remove"
                                                onClick={() => remove(g)}
                                            >
                                                Remove
                                            </Button>
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

SalesGoalsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/sales-goals' },
        { title: 'Sales goals', href: '#' },
    ],
};
