import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useMeasurement } from '@/hooks/use-measurement';
import { wallClock } from '@/lib/datetime';
import { show as bookingShow } from '@/routes/bookings';
import { show as clientShow } from '@/routes/clients';
import { edit, show } from '@/routes/spaces';
import { show as venueShow } from '@/routes/venues';

type Parent = { id: number; name: string };
type Child = { id: number; name: string; capacity: number | null };

type Space = {
    id: number;
    name: string;
    kind: string | null;
    kind_label: string | null;
    capacity: number | null;
    sqft: number | null;
    bookable_unit: string | null;
    image_url: string;
    has_photo: boolean;
    attributes: Record<string, unknown>;
    parent: Parent | null;
    children: Child[];
};

type Venue = { id: number; slug: string; name: string };

type UpcomingBooking = {
    id: number;
    reference: string;
    name: string;
    status: string;
    start_at: string | null;
    end_at: string | null;
    client_name: string | null;
    client_id: number | null;
};

type Blackout = {
    id: number;
    starts_at: string | null;
    ends_at: string | null;
    reason: string;
};

type Stats = {
    upcoming_count: number;
    sub_space_count: number;
};

type Props = {
    space: Space;
    venue: Venue;
    upcoming_bookings: UpcomingBooking[];
    blackouts: Blackout[];
    stats: Stats;
};

const STATUS_COLORS: Record<string, string> = {
    inquiry:
        'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    hold: 'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    tentative: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    definite:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    completed:
        'bg-purple-100 text-purple-900 dark:bg-purple-900/40 dark:text-purple-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
};

function formatDate(iso: string | null): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function SpacesShow({
    space,
    venue,
    upcoming_bookings,
    blackouts,
    stats,
}: Props) {
    const { formatArea } = useMeasurement();
    const attributeEntries = Object.entries(space.attributes ?? {}).filter(
        ([, v]) => v !== null && v !== '' && v !== false,
    );

    return (
        <>
            <Head title={`${space.name} · ${venue.name}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Link
                    href={venueShow(venue.slug).url}
                    className="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    {venue.name}
                </Link>

                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <img
                            src={space.image_url}
                            alt=""
                            className="h-14 w-14 shrink-0 rounded-lg border border-border object-cover sm:h-16 sm:w-16"
                        />
                        <div className="flex flex-col gap-1">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {space.name}
                            </h1>
                            <div className="text-sm text-muted-foreground">
                                {space.kind_label ?? '-'}
                                {' · '}
                                <Link
                                    href={venueShow(venue.slug).url}
                                    className="hover:text-foreground hover:underline"
                                >
                                    {venue.name}
                                </Link>
                                {space.parent ? (
                                    <>
                                        {' · part of '}
                                        <Link
                                            href={show(space.parent.id).url}
                                            className="hover:text-foreground hover:underline"
                                        >
                                            {space.parent.name}
                                        </Link>
                                    </>
                                ) : null}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(space.id).url}>Edit</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href={`/admin/spaces/${space.id}/constraints`}>
                                Floor plan
                            </a>
                        </Button>
                    </div>
                </header>

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
                        <Detail
                            label="Capacity"
                            value={
                                space.capacity
                                    ? space.capacity.toLocaleString()
                                    : '-'
                            }
                        />
                        <Detail label="Area" value={formatArea(space.sqft)} />
                        <Detail
                            label="Bookable unit"
                            value={space.bookable_unit ?? '-'}
                        />
                        <Detail
                            label="Sub-spaces"
                            value={stats.sub_space_count.toLocaleString()}
                        />
                        <Detail
                            label="Upcoming"
                            value={stats.upcoming_count.toLocaleString()}
                            sub="next on the calendar"
                        />
                    </CardContent>
                </Card>

                {attributeEntries.length > 0 ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">
                                Attributes
                            </h2>
                            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                {attributeEntries.map(([key, value]) => (
                                    <div
                                        key={key}
                                        className="flex justify-between gap-3 border-b border-sidebar-border/40 py-1"
                                    >
                                        <dt className="text-muted-foreground">
                                            {key.replace(/_/g, ' ')}
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {String(value)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                ) : null}

                {space.children.length > 0 ? (
                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4">
                            <h2 className="text-sm font-semibold">
                                Sub-spaces
                            </h2>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {space.children.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={show(c.id).url}
                                        className="flex items-center justify-between gap-2 rounded-md border border-border p-2 text-sm transition-colors hover:bg-muted"
                                    >
                                        <span className="truncate font-medium">
                                            {c.name}
                                        </span>
                                        {c.capacity ? (
                                            <span className="shrink-0 text-xs text-muted-foreground">
                                                cap {c.capacity}
                                            </span>
                                        ) : null}
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">
                                Upcoming bookings
                            </h2>
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {upcoming_bookings.length}
                                </span>
                                <a
                                    href={`/bookings/create?venue_id=${venue.id}&space_id=${space.id}`}
                                    className="rounded-md border border-border px-2 py-0.5 text-xs font-medium hover:bg-muted"
                                    data-tour-id="space-add-booking"
                                >
                                    + Add booking
                                </a>
                            </div>
                        </div>
                        {upcoming_bookings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No upcoming bookings for this space.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {upcoming_bookings.map((b) => (
                                    <li
                                        key={b.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <Link
                                            href={bookingShow(b.id).url}
                                            className="min-w-0 flex-1 hover:underline"
                                        >
                                            <div className="font-medium">
                                                {b.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                <span className="font-mono">
                                                    {b.reference}
                                                </span>
                                                {' · '}
                                                {formatDate(b.start_at)}
                                            </div>
                                        </Link>
                                        {b.client_id ? (
                                            <Link
                                                href={
                                                    clientShow(b.client_id).url
                                                }
                                                className="hidden text-xs text-muted-foreground hover:underline sm:inline"
                                            >
                                                {b.client_name}
                                            </Link>
                                        ) : (
                                            <span className="hidden text-xs text-muted-foreground sm:inline">
                                                {b.client_name ?? '-'}
                                            </span>
                                        )}
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[b.status] ?? ''}`}
                                        >
                                            {b.status}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Blackouts</h2>
                            <span className="text-xs text-muted-foreground">
                                {blackouts.length}
                            </span>
                        </div>
                        {blackouts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No active or upcoming blackouts. Manage
                                blackouts from the{' '}
                                <Link
                                    href={venueShow(venue.slug).url}
                                    className="hover:text-foreground hover:underline"
                                >
                                    venue page
                                </Link>
                                .
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {blackouts.map((b) => (
                                    <li
                                        key={b.id}
                                        className="rounded-md border border-border p-2"
                                    >
                                        <div className="font-medium">
                                            {b.reason}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {formatDate(b.starts_at)} -{' '}
                                            {formatDate(b.ends_at)}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function Detail({
    label,
    value,
    sub,
}: {
    label: string;
    value: string;
    sub?: string | null;
}) {
    return (
        <div>
            <div className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-medium">{value}</div>
            {sub ? (
                <div className="text-xs text-muted-foreground">{sub}</div>
            ) : null}
        </div>
    );
}

SpacesShow.layout = {
    breadcrumbs: [{ title: 'Venues', href: '/venues' }],
};
