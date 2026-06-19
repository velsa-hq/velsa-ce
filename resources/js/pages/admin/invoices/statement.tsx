import { Head, Link } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type InvoiceLine = {
    id: number;
    number: string;
    status: string | null;
    status_label: string | null;
    dunning_label: string | null;
    issued_on: string | null;
    due_on: string | null;
    total_cents: number;
    paid_cents: number;
    balance_cents: number;
    days_past_due: number;
};

type Exhibitor = {
    id: number;
    company_name: string;
    contact_name: string;
    email: string;
    phone: string | null;
    booth_assignment: string | null;
};

type Props = {
    exhibitor: Exhibitor;
    invoices: InvoiceLine[];
    totals: {
        total_cents: number;
        paid_cents: number;
        balance_cents: number;
        past_due_cents: number;
    };
    generated_at: string;
};

function money(cents: number): string {
    return `$${(cents / 100).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

export default function ExhibitorStatement({
    exhibitor,
    invoices,
    totals,
    generated_at,
}: Props) {
    return (
        <>
            <Head title={`Statement · ${exhibitor.company_name}`} />

            <div className="mb-6 flex items-center justify-between border-b border-border bg-card px-4 py-3 print:hidden">
                <Button asChild variant="ghost" size="sm">
                    <Link href={`/exhibitors/${exhibitor.id}`}>
                        Back to exhibitor
                    </Link>
                </Button>
                <Button onClick={() => window.print()} size="sm">
                    <Printer className="size-4" /> Print / save as PDF
                </Button>
            </div>

            <article className="mx-auto max-w-4xl bg-white p-8 text-black print:p-0 print:shadow-none">
                <header className="mb-6 flex items-start justify-between border-b border-neutral-300 pb-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            STATEMENT
                        </h1>
                        <p className="mt-1 text-sm text-neutral-600">
                            Generated {new Date(generated_at).toLocaleString()}
                        </p>
                    </div>
                    <div className="text-right">
                        <div className="text-lg font-semibold">Velsa</div>
                    </div>
                </header>

                <section className="mb-6">
                    <h2 className="mb-2 text-xs font-semibold tracking-wider text-neutral-500 uppercase">
                        For
                    </h2>
                    <div className="font-semibold">
                        {exhibitor.company_name}
                    </div>
                    <div className="text-sm text-neutral-700">
                        {exhibitor.contact_name}
                    </div>
                    <div className="text-sm text-neutral-700">
                        {exhibitor.email}
                    </div>
                    {exhibitor.phone && (
                        <div className="text-sm text-neutral-700">
                            {exhibitor.phone}
                        </div>
                    )}
                </section>

                <section className="mb-6 grid grid-cols-4 gap-3">
                    <div className="rounded-lg bg-neutral-100 p-3">
                        <div className="text-xs tracking-wider text-neutral-500 uppercase">
                            Total billed
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(totals.total_cents)}
                        </div>
                    </div>
                    <div className="rounded-lg bg-neutral-100 p-3">
                        <div className="text-xs tracking-wider text-neutral-500 uppercase">
                            Paid
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(totals.paid_cents)}
                        </div>
                    </div>
                    <div className="rounded-lg bg-neutral-100 p-3">
                        <div className="text-xs tracking-wider text-neutral-500 uppercase">
                            Balance
                        </div>
                        <div className="mt-1 font-semibold tabular-nums">
                            {money(totals.balance_cents)}
                        </div>
                    </div>
                    <div className="rounded-lg bg-amber-50 p-3">
                        <div className="text-xs tracking-wider text-amber-900 uppercase">
                            Past due
                        </div>
                        <div className="mt-1 font-semibold text-amber-900 tabular-nums">
                            {money(totals.past_due_cents)}
                        </div>
                    </div>
                </section>

                <table className="w-full text-sm">
                    <thead className="border-y-2 border-neutral-700 bg-neutral-100">
                        <tr>
                            <th className="px-3 py-2 text-left font-semibold">
                                Invoice
                            </th>
                            <th className="px-3 py-2 text-left font-semibold">
                                Status
                            </th>
                            <th className="px-3 py-2 text-left font-semibold">
                                Issued
                            </th>
                            <th className="px-3 py-2 text-left font-semibold">
                                Due
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Total
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Paid
                            </th>
                            <th className="px-3 py-2 text-right font-semibold">
                                Balance
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.map((inv) => (
                            <tr
                                key={inv.id}
                                className="border-b border-neutral-200"
                            >
                                <td className="px-3 py-2 font-mono">
                                    {inv.number}
                                </td>
                                <td className="px-3 py-2">
                                    <Badge variant="secondary">
                                        {inv.status_label}
                                    </Badge>
                                </td>
                                <td className="px-3 py-2">
                                    {inv.issued_on ?? '-'}
                                </td>
                                <td className="px-3 py-2">
                                    {inv.due_on ?? '-'}
                                    {inv.days_past_due > 0 && (
                                        <span className="ml-1 text-xs text-amber-700">
                                            ({inv.days_past_due}d)
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {money(inv.total_cents)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {money(inv.paid_cents)}
                                </td>
                                <td className="px-3 py-2 text-right tabular-nums">
                                    {money(inv.balance_cents)}
                                </td>
                            </tr>
                        ))}
                        {invoices.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-3 py-6 text-center text-sm text-neutral-500"
                                >
                                    No invoices on file for this exhibitor.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                <footer className="mt-12 border-t border-neutral-300 pt-4 text-center text-xs text-neutral-500">
                    Please remit payment for any past-due balance. Questions?
                    Contact your event coordinator.
                </footer>
            </article>

            <style>{`
                @media print {
                    body { background: white !important; }
                    nav, header.border-b, footer.border-t { display: none !important; }
                }
            `}</style>
        </>
    );
}
