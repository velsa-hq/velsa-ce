import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { archive, index, restore, show } from '@/routes/clients';

type Row = {
    id: number;
    name: string;
    type: string | null;
    industry: string | null;
    primary_contact: { name: string; email: string | null } | null;
    contact_count: number;
    lead_count: number;
    booking_count: number;
    retired_at: string | null;
};

type Props = {
    clients: {
        data: Row[];
        meta: { total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { q: string | null };
};

export default function ClientsArchive({ clients, filters }: Props) {
    const [q, setQ] = useState(filters.q ?? '');

    const search = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(archive().url, q ? { q } : {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Retired clients" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Retired clients
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {clients.meta.total} retired · restore any to bring
                            it back to the active list.
                        </p>
                    </div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to clients
                    </Link>
                </header>

                <form
                    onSubmit={search}
                    className="flex max-w-md items-center gap-2"
                >
                    <Input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Search retired clients by name..."
                        data-tour-id="client-archive-search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {clients.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground italic dark:border-sidebar-border">
                        No retired clients
                        {filters.q ? ' match that search' : ''}.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Client
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Primary contact
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Records
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Retired
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {clients.data.map((c) => (
                                    <tr key={c.id} className="align-top">
                                        <td className="px-3 py-2">
                                            <Link
                                                href={show(c.id).url}
                                                className="font-medium hover:underline"
                                            >
                                                {c.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {c.industry ?? '-'}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {c.primary_contact ? (
                                                <>
                                                    <div>
                                                        {c.primary_contact.name}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {c.primary_contact
                                                            .email ?? '-'}
                                                    </div>
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right text-xs text-muted-foreground">
                                            {c.contact_count} contacts ·{' '}
                                            {c.lead_count} leads ·{' '}
                                            {c.booking_count} bookings
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {c.retired_at ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.patch(
                                                            restore(c.id).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Restore
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex gap-2">
                    {clients.links.prev ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={clients.links.prev}>Previous</Link>
                        </Button>
                    ) : null}
                    {clients.links.next ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={clients.links.next}>Next</Link>
                        </Button>
                    ) : null}
                </div>
            </div>
        </>
    );
}

ClientsArchive.layout = {
    breadcrumbs: [
        { title: 'Clients', href: '/clients' },
        { title: 'Retired', href: '/clients/archive' },
    ],
};
