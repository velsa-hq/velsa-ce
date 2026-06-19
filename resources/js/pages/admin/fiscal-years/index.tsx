import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
    budgets_count: number;
};

type Props = { years: Year[] };

function defaultLabel(): string {
    const y = new Date().getFullYear() + 1;

    return `FY${String(y).slice(-2)}`;
}

function defaultStart(): string {
    return `${new Date().getFullYear()}-10-01`;
}

function defaultEnd(): string {
    return `${new Date().getFullYear() + 1}-09-30`;
}

export default function FiscalYearsIndex({ years }: Props) {
    const [label, setLabel] = useState(defaultLabel());
    const [startsOn, setStartsOn] = useState(defaultStart());
    const [endsOn, setEndsOn] = useState(defaultEnd());

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            '/admin/fiscal-years',
            { label, starts_on: startsOn, ends_on: endsOn },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setLabel(defaultLabel());
                    setStartsOn(defaultStart());
                    setEndsOn(defaultEnd());
                },
            },
        );
    };

    return (
        <>
            <Head title="Fiscal years · Admin" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Fiscal years
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Defined fiscal years drive budget tracking and
                        over/under reports against actual journal entries. Many
                        government agencies run Oct 1 to Sep 30.
                    </p>
                </header>

                <section className="rounded-xl border border-border bg-card p-4">
                    <h2 className="mb-3 text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                        Add fiscal year
                    </h2>
                    <form
                        onSubmit={submit}
                        className="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end"
                    >
                        <div className="grid gap-1">
                            <Label htmlFor="label">Label</Label>
                            <Input
                                id="label"
                                required
                                value={label}
                                onChange={(e) => setLabel(e.target.value)}
                                placeholder="FY26"
                                maxLength={16}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="starts">Starts on</Label>
                            <Input
                                id="starts"
                                type="date"
                                required
                                value={startsOn}
                                onChange={(e) => setStartsOn(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="ends">Ends on</Label>
                            <Input
                                id="ends"
                                type="date"
                                required
                                value={endsOn}
                                onChange={(e) => setEndsOn(e.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            data-tour-id="fiscal-years-create"
                        >
                            Create
                        </Button>
                    </form>
                </section>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table
                        className="w-full text-sm"
                        data-tour-id="fiscal-years-list"
                    >
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Label
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Window
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Budget lines
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {years.map((y) => (
                                <tr
                                    key={y.id}
                                    className="border-t border-border/60 hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3 font-mono">
                                        <Link
                                            href={`/admin/fiscal-years/${y.label}`}
                                            className="hover:underline"
                                        >
                                            {y.label}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {y.starts_on} - {y.ends_on}
                                    </td>
                                    <td className="px-4 py-3">
                                        {y.is_closed ? (
                                            <Badge variant="secondary">
                                                Closed
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Open
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right tabular-nums">
                                        {y.budgets_count}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            data-tour-id="fiscal-years-manage"
                                        >
                                            <Link
                                                href={`/admin/fiscal-years/${y.label}`}
                                            >
                                                Manage
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {years.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No fiscal years yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

FiscalYearsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/fiscal-years' },
        { title: 'Fiscal years', href: '/admin/fiscal-years' },
    ],
};
