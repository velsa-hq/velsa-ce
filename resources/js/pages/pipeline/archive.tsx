import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { clone, reopen } from '@/routes/leads';
import { archive, index } from '@/routes/pipeline';

type ArchivedLead = {
    id: number;
    name: string;
    stage: string | null;
    is_won: boolean;
    client_name: string | null;
    venue_name: string | null;
    estimated_cents: number;
    lost_reason: string | null;
    closed_at: string | null;
    archived_at: string | null;
    can_reopen: boolean;
    converted_booking: { id: number; reference: string } | null;
};

type Props = {
    leads: ArchivedLead[];
    filters: { q: string };
};

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

export default function PipelineArchive({ leads, filters }: Props) {
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
            <Head title="Pipeline archive" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Pipeline archive
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Closed opportunities that have aged off the active
                            board. Reopen one to work it again, or clone it to
                            start a fresh pursuit.
                        </p>
                    </div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to pipeline
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
                        placeholder="Search by opportunity or client name..."
                        data-tour-id="archive-search"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {leads.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-sidebar-border/70 p-8 text-center text-sm text-muted-foreground italic dark:border-sidebar-border">
                        Nothing in the archive
                        {filters.q ? ' matches that search' : ' yet'}.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        Opportunity
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Outcome
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Value
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        Closed
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                                {leads.map((lead) => (
                                    <tr key={lead.id} className="align-top">
                                        <td className="px-3 py-2">
                                            <Link
                                                href={`/leads/${lead.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {lead.name}
                                            </Link>
                                            <div className="text-xs text-muted-foreground">
                                                {lead.client_name ?? '-'}
                                                {lead.venue_name
                                                    ? ` · ${lead.venue_name}`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            {lead.is_won ? (
                                                <span className="inline-flex items-center rounded-sm bg-emerald-100 px-1.5 py-px text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                    Won
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center rounded-sm bg-rose-100 px-1.5 py-px text-xs font-medium text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                                                    Lost
                                                </span>
                                            )}
                                            {lead.lost_reason ? (
                                                <div className="mt-0.5 text-xs text-muted-foreground">
                                                    {lead.lost_reason}
                                                </div>
                                            ) : null}
                                            {lead.converted_booking ? (
                                                <Link
                                                    href={`/bookings/${lead.converted_booking.id}`}
                                                    className="mt-0.5 block text-xs text-emerald-700 hover:underline dark:text-emerald-400"
                                                >
                                                    ✓{' '}
                                                    {
                                                        lead.converted_booking
                                                            .reference
                                                    }
                                                </Link>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2 text-right font-mono text-xs">
                                            {money(lead.estimated_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-xs text-muted-foreground">
                                            {lead.closed_at ?? '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex justify-end gap-2">
                                                {lead.can_reopen ? (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            router.patch(
                                                                reopen(lead.id)
                                                                    .url,
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Reopen
                                                    </Button>
                                                ) : null}
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            clone(lead.id).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Clone
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

PipelineArchive.layout = {
    breadcrumbs: [
        { title: 'Pipeline', href: '/pipeline' },
        { title: 'Archive', href: '/pipeline/archive' },
    ],
};
