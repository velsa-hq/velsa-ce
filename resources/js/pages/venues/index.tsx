import { Head, Link, usePage } from '@inertiajs/react';
import HelpLink from '@/components/help-link';
import { Button } from '@/components/ui/button';
import { archive, create, show } from '@/routes/venues';

type VenueRow = {
    id: number;
    slug: string;
    name: string;
    city: string | null;
    state: string | null;
    timezone: string;
    space_count: number;
    status: 'active' | 'coming_soon' | 'retired';
    summary: string | null;
    image_url: string;
};

type Props = {
    venues: VenueRow[];
};

const STATUS_LABEL: Record<VenueRow['status'], string> = {
    active: 'Active',
    coming_soon: 'Coming soon',
    retired: 'Retired',
};

const STATUS_CLASSES: Record<VenueRow['status'], string> = {
    active: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    coming_soon:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    retired:
        'bg-neutral-200 text-neutral-700 dark:bg-neutral-700/60 dark:text-neutral-200',
};

export default function VenuesIndex({ venues }: Props) {
    const activeCount = venues.filter((v) => v.status === 'active').length;
    const comingSoonCount = venues.filter(
        (v) => v.status === 'coming_soon',
    ).length;

    // Org name from shared branding (e.g. the demo's "Sentinel Bay County
    // Tourism & Convention Bureau"); falls back to the short brand name.
    const props = usePage().props as unknown as {
        branding?: { app_title?: string };
        name?: string;
    };
    const orgName = props.branding?.app_title || props.name || 'Venues';

    return (
        <>
            <Head title="Venues" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                            Venues
                            <HelpLink slug="venues" />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {orgName} · {activeCount} active · {comingSoonCount}{' '}
                            coming soon
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={archive().url}>Archive</Link>
                        </Button>
                        <Button asChild size="sm">
                            <Link href={create().url} data-tour-id="venue-new">
                                + New venue
                            </Link>
                        </Button>
                    </div>
                </header>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {venues.map((venue) => (
                        <Link
                            key={venue.id}
                            href={show(venue.slug).url}
                            className="flex overflow-hidden rounded-xl border border-sidebar-border/70 transition-colors hover:bg-muted dark:border-sidebar-border"
                        >
                            <img
                                src={venue.image_url}
                                alt=""
                                className="w-28 shrink-0 self-stretch bg-muted object-cover sm:w-32"
                            />
                            <div className="flex min-w-0 flex-1 flex-col gap-2 p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex min-w-0 flex-col gap-0.5">
                                        <h2 className="truncate text-base leading-tight font-semibold">
                                            {venue.name}
                                        </h2>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {[venue.city, venue.state]
                                                .filter(Boolean)
                                                .join(', ') || venue.timezone}
                                        </p>
                                    </div>
                                    <span
                                        className={`shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[venue.status]}`}
                                    >
                                        {STATUS_LABEL[venue.status]}
                                    </span>
                                </div>

                                {venue.summary && (
                                    <p className="line-clamp-2 text-sm leading-snug text-muted-foreground">
                                        {venue.summary}
                                    </p>
                                )}

                                <div className="mt-auto flex items-center justify-between gap-2 border-t border-sidebar-border/40 pt-2 text-xs text-muted-foreground dark:border-sidebar-border/60">
                                    <span className="truncate font-mono">
                                        {venue.slug}
                                    </span>
                                    <span className="shrink-0">
                                        {venue.space_count}{' '}
                                        {venue.space_count === 1
                                            ? 'space'
                                            : 'spaces'}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

VenuesIndex.layout = {
    breadcrumbs: [
        {
            title: 'Venues',
            href: '/venues',
        },
    ],
};
