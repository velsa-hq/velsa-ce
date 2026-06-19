import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { archive, index, restore, show } from '@/routes/contracts';

type Row = {
    id: number;
    reference: string;
    status: string | null;
    kind: string;
    total_cents: number;
    deleted_at: string | null;
    booking: {
        reference: string;
        name: string;
        venue_name: string | null;
        client_name: string | null;
    } | null;
};

type Props = {
    contracts: {
        data: Row[];
        meta: { total: number };
        links: { prev: string | null; next: string | null };
    };
    filters: { q: string | null };
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

export default function ContractsArchive({ contracts, filters }: Props) {
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
            <Head title="Deleted contracts" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Deleted contracts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {contracts.meta.total} deleted · restore any to
                            bring it back to the active list.
                        </p>
                    </div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to contracts
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
                        placeholder="Search by contract reference..."
                        data-tour-id="ct-archive-search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {contracts.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground italic dark:border-sidebar-border">
                        No deleted contracts
                        {filters.q ? ' match that search' : ''}.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Contract
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Booking
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Value
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Deleted
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {contracts.data.map((c) => (
                                    <tr key={c.id} className="align-top">
                                        <td className="px-3 py-2">
                                            <Link
                                                href={show(c.id).url}
                                                className="font-mono text-xs hover:underline"
                                            >
                                                {c.reference}
                                            </Link>
                                            <div className="text-[10px] text-muted-foreground">
                                                {c.kind}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-xs">
                                            {c.booking ? (
                                                <>
                                                    <div>{c.booking.name}</div>
                                                    <div className="text-muted-foreground">
                                                        {c.booking
                                                            .client_name ?? '-'}
                                                        {c.booking.venue_name
                                                            ? ` · ${c.booking.venue_name}`
                                                            : ''}
                                                    </div>
                                                </>
                                            ) : (
                                                '-'
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-xs capitalize">
                                            {c.status?.replace('_', ' ') ?? '-'}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {money(c.total_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {c.deleted_at ?? '-'}
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
                    {contracts.links.prev ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={contracts.links.prev}>Previous</Link>
                        </Button>
                    ) : null}
                    {contracts.links.next ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={contracts.links.next}>Next</Link>
                        </Button>
                    ) : null}
                </div>
            </div>
        </>
    );
}

ContractsArchive.layout = {
    breadcrumbs: [
        { title: 'Contracts', href: '/contracts' },
        { title: 'Deleted', href: '/contracts/archive' },
    ],
};
