import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { index, restore } from '@/routes/venues';

type VenueRow = {
    id: number;
    slug: string;
    name: string;
    city: string | null;
    state: string | null;
    space_count: number;
    retired_at: string | null;
};

type Props = { venues: VenueRow[] };

function formatDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function VenuesArchive({ venues }: Props) {
    return (
        <>
            <Head title="Archived venues" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Archived venues
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Retired venues - restore one to bring it back to the
                            active list.
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={index().url}>Back to venues</Link>
                    </Button>
                </header>

                {venues.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No archived venues.
                    </p>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {venues.map((venue) => (
                            <div
                                key={venue.id}
                                className="flex flex-col gap-2 rounded-xl border border-border p-4"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <h2 className="text-base leading-tight font-semibold">
                                        {venue.name}
                                    </h2>
                                    <span className="shrink-0 rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-700/60 dark:text-neutral-200">
                                        Retired
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {[venue.city, venue.state]
                                        .filter(Boolean)
                                        .join(', ') || '-'}{' '}
                                    · {venue.space_count}{' '}
                                    {venue.space_count === 1
                                        ? 'space'
                                        : 'spaces'}
                                </p>
                                <div className="mt-auto flex items-center justify-between border-t border-border pt-2 text-xs text-muted-foreground">
                                    <span>
                                        Retired {formatDate(venue.retired_at)}
                                    </span>
                                    <Form
                                        {...restore.form(venue.slug)}
                                        options={{ preserveScroll: true }}
                                    >
                                        <button
                                            type="submit"
                                            className="font-medium text-primary hover:underline"
                                        >
                                            Restore
                                        </button>
                                    </Form>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

VenuesArchive.layout = {
    breadcrumbs: [
        { title: 'Venues', href: '/venues' },
        { title: 'Archive', href: '/venues/archive' },
    ],
};
