import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import { archive, create } from '@/routes/clients';

type ClientRow = {
    id: number;
    name: string;
    type: string | null;
    industry: string | null;
    source: string | null;
    primary_contact: {
        name: string;
        email: string | null;
        phone: string | null;
    } | null;
    contact_count: number;
    lead_count: number;
    open_pipeline_cents: number;
};

type Props = {
    clients: {
        data: ClientRow[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
        links: { prev: string | null; next: string | null };
    };
    filters: { q: string | null; type: string | null };
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

const TYPE_LABEL: Record<string, string> = {
    individual: 'Individual',
    business: 'Business',
    government: 'Government',
    nonprofit: 'Non-profit',
    educational: 'Educational',
};

export default function ClientsIndex({ clients, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const [type, setType] = useState(filters.type ?? '');

    const applyFilters = () => {
        const params: Record<string, string> = {};

        if (q) {
            params.q = q;
        }

        if (type) {
            params.type = type;
        }

        router.get('/clients', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Clients" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Clients
                            <HelpLink slug="clients" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {clients.meta.total}{' '}
                            {clients.meta.total === 1 ? 'client' : 'clients'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Link
                            href={archive().url}
                            className="text-sm text-muted-foreground hover:text-foreground hover:underline"
                        >
                            Retired
                        </Link>
                        <Button asChild size="sm">
                            <Link href={create().url} data-tour-id="client-new">
                                + New client
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="flex flex-wrap gap-2 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                    <input
                        type="text"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        placeholder="Search by name..."
                        className="flex-1 rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                    />
                    <select
                        value={type}
                        onChange={(e) => setType(e.target.value)}
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                    >
                        <option value="">Any type</option>
                        {Object.entries(TYPE_LABEL).map(([v, label]) => (
                            <option key={v} value={v}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <Button size="sm" onClick={applyFilters}>
                        Apply
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium">
                                    Client
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Type
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Industry
                                </th>
                                <th className="px-3 py-2 text-left font-medium">
                                    Primary contact
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Leads
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Open pipeline
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No clients match.
                                    </td>
                                </tr>
                            ) : (
                                clients.data.map((c, idx) => (
                                    <tr
                                        key={c.id}
                                        className={
                                            idx % 2 === 0
                                                ? 'border-t border-sidebar-border/40 dark:border-sidebar-border/60'
                                                : 'border-t border-sidebar-border/40 bg-muted/20 dark:border-sidebar-border/60'
                                        }
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            <Link
                                                href={`/clients/${c.id}`}
                                                className="hover:underline"
                                            >
                                                {c.name}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {c.type
                                                ? (TYPE_LABEL[c.type] ?? c.type)
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {c.industry ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {c.primary_contact ? (
                                                <span>
                                                    {c.primary_contact.name}
                                                    {c.primary_contact.email
                                                        ? ` · ${c.primary_contact.email}`
                                                        : ''}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground italic">
                                                    no contact
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {c.lead_count}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {c.open_pipeline_cents > 0
                                                ? money(c.open_pipeline_cents)
                                                : '-'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex items-center justify-between text-xs text-muted-foreground">
                    <span>
                        Page {clients.meta.current_page} of{' '}
                        {clients.meta.last_page}
                    </span>
                    <div className="flex gap-2">
                        {clients.links.prev ? (
                            <Link
                                href={clients.links.prev}
                                preserveScroll
                                className="rounded border border-sidebar-border/70 px-2 py-1 dark:border-sidebar-border"
                            >
                                Prev
                            </Link>
                        ) : null}
                        {clients.links.next ? (
                            <Link
                                href={clients.links.next}
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

ClientsIndex.layout = {
    breadcrumbs: [{ title: 'Clients', href: '/clients' }],
};
