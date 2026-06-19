import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useMeasurement } from '@/hooks/use-measurement';
import { wallClock } from '@/lib/datetime';
import { show as bookingShow } from '@/routes/bookings';
import { show as clientShow } from '@/routes/clients';
import { destroy, edit, index } from '@/routes/venues';
import {
    destroy as destroyBlackout,
    store as storeBlackout,
} from '@/routes/venues/blackouts';

type Space = {
    id: number;
    name: string;
    kind: string | null;
    kind_label: string | null;
    sqft: number | null;
    capacity: number | null;
    bookable_unit: string | null;
    image_url: string;
};

type UpcomingBooking = {
    id: number;
    reference: string;
    name: string;
    status: string;
    start_at: string | null;
    end_at: string | null;
    total_cents: number;
    client_name: string | null;
    client_id: number | null;
};

type WorkOrderTemplate = {
    id: number;
    name: string;
    kind: string | null;
    recurrence_rrule: string | null;
    lookahead_days: number | null;
};

type WorkOrderRow = {
    id: number;
    reference: string;
    title: string;
    kind: string | null;
    status: string;
    priority: number;
    scheduled_for: string | null;
    assignee_name: string | null;
    is_overdue: boolean;
};

type Venue = {
    id: number;
    slug: string;
    name: string;
    building: string | null;
    street: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    phone: string | null;
    website: string | null;
    timezone: string;
    summary: string | null;
    is_active: boolean;
    active_at: string | null;
    retired_at: string | null;
    image_url: string;
    spaces: Space[];
};

type Stats = {
    lifetime_bookings: number;
    confirmed_revenue_cents: number;
    upcoming_count: number;
    space_count: number;
    total_capacity: number;
};

type Blackout = {
    id: number;
    scope: 'venue' | 'space';
    space_id: number | null;
    space_name: string | null;
    starts_at: string | null;
    ends_at: string | null;
    reason: string;
};

type Props = {
    venue: Venue;
    upcoming_bookings: UpcomingBooking[];
    work_order_templates: WorkOrderTemplate[];
    work_orders: WorkOrderRow[];
    blackouts: Blackout[];
    stats: Stats;
};

const WORK_ORDER_STATUS_COLORS: Record<string, string> = {
    draft: 'bg-neutral-200 text-neutral-800 dark:bg-neutral-700 dark:text-neutral-100',
    open: 'bg-blue-100 text-blue-900 dark:bg-blue-900/40 dark:text-blue-100',
    assigned: 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100',
    in_progress:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-100',
    completed:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100',
    cancelled:
        'bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-100',
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

function money(cents: number): string {
    return (cents / 100).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    });
}

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

function describeRrule(rrule: string | null): string {
    if (!rrule) {
        return '-';
    }

    const parts = Object.fromEntries(
        rrule.split(';').map((p) => {
            const [k, v] = p.split('=');

            return [k, v];
        }),
    );
    const freq = parts.FREQ?.toLowerCase();
    const interval = parts.INTERVAL ? Number(parts.INTERVAL) : 1;

    if (freq === 'weekly') {
        return parts.BYDAY ? `Weekly on ${parts.BYDAY}` : 'Weekly';
    }

    if (freq === 'monthly') {
        if (interval > 1) {
            return `Every ${interval} months`;
        }

        return 'Monthly';
    }

    if (freq === 'daily') {
        return interval > 1 ? `Every ${interval} days` : 'Daily';
    }

    return freq ?? '-';
}

export default function VenuesShow({
    venue,
    upcoming_bookings,
    work_order_templates,
    work_orders,
    blackouts,
    stats,
}: Props) {
    const [showBlackoutForm, setShowBlackoutForm] = useState(false);
    const { formatArea } = useMeasurement();

    return (
        <>
            <Head title={`${venue.name} · Venue`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Link
                    href={index().url}
                    className="inline-flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    Venues
                </Link>

                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <img
                            src={venue.image_url}
                            alt=""
                            className="h-14 w-14 shrink-0 rounded-lg border border-border object-cover sm:h-16 sm:w-16"
                        />
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-3">
                                <h1 className="text-2xl font-semibold tracking-tight">
                                    {venue.name}
                                </h1>
                                {venue.is_active ? (
                                    <span className="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100">
                                        Active
                                    </span>
                                ) : venue.retired_at ? (
                                    <span className="inline-flex items-center rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium dark:bg-neutral-700 dark:text-neutral-100">
                                        Retired
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-100">
                                        Coming soon
                                    </span>
                                )}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                <span className="font-mono">{venue.slug}</span>
                                {venue.city ? (
                                    <>
                                        {' · '}
                                        {venue.city}
                                        {venue.state ? `, ${venue.state}` : ''}
                                    </>
                                ) : null}
                                {' · '}
                                {venue.timezone}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href={edit(venue.slug).url}>Edit</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                if (
                                    confirm(
                                        `Archive "${venue.name}"? It will be hidden from the active list (restorable from the archive).`,
                                    )
                                ) {
                                    router.delete(destroy(venue.slug).url);
                                }
                            }}
                        >
                            Archive
                        </Button>
                    </div>
                </header>

                <Card>
                    <CardContent className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5">
                        <Detail
                            label="Lifetime bookings"
                            value={stats.lifetime_bookings.toLocaleString()}
                        />
                        <Detail
                            label="Confirmed revenue"
                            value={
                                stats.confirmed_revenue_cents > 0
                                    ? money(stats.confirmed_revenue_cents)
                                    : '-'
                            }
                            sub="definite + completed"
                        />
                        <Detail
                            label="Upcoming"
                            value={stats.upcoming_count.toLocaleString()}
                            sub="next on the calendar"
                        />
                        <Detail
                            label="Spaces"
                            value={stats.space_count.toLocaleString()}
                        />
                        <Detail
                            label="Total capacity"
                            value={
                                stats.total_capacity > 0
                                    ? stats.total_capacity.toLocaleString()
                                    : '-'
                            }
                        />
                    </CardContent>
                </Card>

                {venue.summary ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4">
                            <h2 className="text-sm font-semibold">About</h2>
                            <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                {venue.summary}
                            </p>
                        </CardContent>
                    </Card>
                ) : null}

                {venue.building ||
                venue.street ||
                venue.zip ||
                venue.phone ||
                venue.website ? (
                    <Card>
                        <CardContent className="flex flex-col gap-2 p-4 text-sm">
                            <h2 className="text-sm font-semibold">
                                Location &amp; contact
                            </h2>
                            {venue.building || venue.street || venue.zip ? (
                                <p className="text-muted-foreground">
                                    {[
                                        venue.building,
                                        venue.street,
                                        [
                                            [venue.city, venue.state]
                                                .filter(Boolean)
                                                .join(', '),
                                            venue.zip,
                                        ]
                                            .filter(Boolean)
                                            .join(' '),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            ) : null}
                            {venue.phone ? (
                                <p className="text-muted-foreground">
                                    {venue.phone}
                                </p>
                            ) : null}
                            {venue.website ? (
                                /^https?:\/\//i.test(venue.website) ? (
                                    <a
                                        href={venue.website}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-primary hover:underline"
                                    >
                                        {venue.website}
                                    </a>
                                ) : (
                                    // Defense in depth: never render a non-http(s)
                                    // scheme (e.g. javascript:) as a clickable href.
                                    <p className="text-muted-foreground">
                                        {venue.website}
                                    </p>
                                )
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Spaces</h2>
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {venue.spaces.length}
                                </span>
                                <a
                                    href={`/venues/${venue.slug}/spaces/create`}
                                    className="rounded-md border border-border px-2 py-0.5 text-xs font-medium hover:bg-muted"
                                    data-tour-id="venue-add-space"
                                >
                                    + Add space
                                </a>
                            </div>
                        </div>
                        {venue.spaces.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No spaces configured yet.
                            </p>
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {venue.spaces.map((s) => (
                                    <div
                                        key={s.id}
                                        className="flex overflow-hidden rounded-md border border-border text-sm"
                                    >
                                        <Link
                                            href={`/spaces/${s.id}`}
                                            className="w-20 shrink-0 self-stretch sm:w-24"
                                            aria-label={`View ${s.name}`}
                                        >
                                            <img
                                                src={s.image_url}
                                                alt=""
                                                className="h-full w-full bg-muted object-cover"
                                            />
                                        </Link>
                                        <div className="flex min-w-0 flex-1 flex-col gap-1.5 p-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <Link
                                                    href={`/spaces/${s.id}`}
                                                    className="min-w-0 truncate font-medium hover:underline"
                                                    data-tour-id="venue-space-open"
                                                >
                                                    {s.name}
                                                </Link>
                                                <div className="flex shrink-0 gap-1">
                                                    <a
                                                        href={`/spaces/${s.id}/edit`}
                                                        className="rounded-md border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        title="Edit space details"
                                                        data-tour-id="venue-space-edit"
                                                    >
                                                        Edit
                                                    </a>
                                                    <a
                                                        href={`/admin/spaces/${s.id}/constraints`}
                                                        className="rounded-md border border-border px-1.5 py-0.5 text-[10px] text-muted-foreground hover:bg-muted hover:text-foreground"
                                                        title="Edit floor constraints (walls, columns, outlets)"
                                                    >
                                                        Floor plan
                                                    </a>
                                                </div>
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">
                                                {s.kind_label ??
                                                    s.kind?.replace('_', ' ') ??
                                                    '-'}
                                            </div>
                                            <div className="mt-auto flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                                                {s.capacity ? (
                                                    <span>
                                                        cap {s.capacity}
                                                    </span>
                                                ) : null}
                                                {s.sqft ? (
                                                    <span>
                                                        {formatArea(s.sqft)}
                                                    </span>
                                                ) : null}
                                                {s.bookable_unit ? (
                                                    <span>
                                                        {s.bookable_unit}
                                                    </span>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">Blackouts</h2>
                            <Button
                                type="button"
                                variant={
                                    showBlackoutForm ? 'outline' : 'default'
                                }
                                size="sm"
                                onClick={() => setShowBlackoutForm((s) => !s)}
                            >
                                {showBlackoutForm ? 'Cancel' : 'Add blackout'}
                            </Button>
                        </div>

                        {showBlackoutForm ? (
                            <Form
                                {...storeBlackout.form(venue.slug)}
                                onSuccess={() => setShowBlackoutForm(false)}
                                className="grid gap-2 rounded-md border border-border p-3 sm:grid-cols-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label className="flex flex-col gap-1 sm:col-span-2">
                                            <span className="text-xs text-muted-foreground">
                                                Scope
                                            </span>
                                            <select
                                                name="space_id"
                                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                                defaultValue=""
                                            >
                                                <option value="">
                                                    Entire venue
                                                </option>
                                                {venue.spaces.map((s) => (
                                                    <option
                                                        key={s.id}
                                                        value={s.id}
                                                    >
                                                        {s.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors.space_id ? (
                                                <span className="text-xs text-rose-600">
                                                    {errors.space_id}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1">
                                            <span className="text-xs text-muted-foreground">
                                                Starts
                                            </span>
                                            <input
                                                type="datetime-local"
                                                name="starts_at"
                                                required
                                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                            />
                                            {errors.starts_at ? (
                                                <span className="text-xs text-rose-600">
                                                    {errors.starts_at}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1">
                                            <span className="text-xs text-muted-foreground">
                                                Ends
                                            </span>
                                            <input
                                                type="datetime-local"
                                                name="ends_at"
                                                required
                                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                            />
                                            {errors.ends_at ? (
                                                <span className="text-xs text-rose-600">
                                                    {errors.ends_at}
                                                </span>
                                            ) : null}
                                        </label>
                                        <label className="flex flex-col gap-1 sm:col-span-2">
                                            <span className="text-xs text-muted-foreground">
                                                Reason
                                            </span>
                                            <input
                                                type="text"
                                                name="reason"
                                                required
                                                maxLength={255}
                                                placeholder="HVAC maintenance"
                                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                            />
                                            {errors.reason ? (
                                                <span className="text-xs text-rose-600">
                                                    {errors.reason}
                                                </span>
                                            ) : null}
                                        </label>
                                        <div className="sm:col-span-2">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Add blackout
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : null}

                        {blackouts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No active or upcoming blackouts.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {blackouts.map((b) => (
                                    <li
                                        key={b.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="font-medium">
                                                {b.reason}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {b.scope === 'venue'
                                                    ? 'Entire venue'
                                                    : (b.space_name ?? 'Space')}
                                                {' · '}
                                                {formatDate(b.starts_at)} -{' '}
                                                {formatDate(b.ends_at)}
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Remove this blackout?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        destroyBlackout([
                                                            venue.slug,
                                                            b.id,
                                                        ]).url,
                                                    );
                                                }
                                            }}
                                        >
                                            Remove
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

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
                                    href={`/bookings/create?venue_id=${venue.id}`}
                                    className="rounded-md border border-border px-2 py-0.5 text-xs font-medium hover:bg-muted"
                                    data-tour-id="venue-add-booking"
                                >
                                    + Add booking
                                </a>
                            </div>
                        </div>
                        {upcoming_bookings.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No upcoming bookings.
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
                                        <span className="font-mono text-xs">
                                            {money(b.total_cents)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="text-sm font-semibold">
                                Active work orders
                            </h2>
                            <div className="flex items-center gap-2">
                                <span className="text-xs text-muted-foreground">
                                    {work_orders.length}
                                </span>
                                <a
                                    href={`/work-orders/create?venue_id=${venue.id}`}
                                    className="rounded-md border border-border px-2 py-0.5 text-xs font-medium hover:bg-muted"
                                    data-tour-id="venue-new-work-order"
                                >
                                    + New work order
                                </a>
                            </div>
                        </div>
                        {work_orders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No open work orders.{' '}
                                <Link
                                    href={`/work-orders?venue_id=${venue.id}`}
                                    className="hover:text-foreground hover:underline"
                                >
                                    View all
                                </Link>
                                .
                            </p>
                        ) : (
                            <>
                                <ul className="flex flex-col gap-2 text-sm">
                                    {work_orders.map((w) => (
                                        <li key={w.id}>
                                            <Link
                                                href={`/work-orders/${w.id}`}
                                                className="flex items-center justify-between gap-3 rounded-md border border-border p-2 transition-colors hover:bg-muted"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium">
                                                        {w.title}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        <span className="font-mono">
                                                            {w.reference}
                                                        </span>
                                                        {' · '}
                                                        {w.scheduled_for
                                                            ? formatDate(
                                                                  w.scheduled_for,
                                                              )
                                                            : 'unscheduled'}
                                                        {w.assignee_name
                                                            ? ` · ${w.assignee_name}`
                                                            : ''}
                                                        {w.is_overdue ? (
                                                            <span className="text-rose-600 dark:text-rose-400">
                                                                {' · overdue'}
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </div>
                                                <span
                                                    className={`inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ${WORK_ORDER_STATUS_COLORS[w.status] ?? ''}`}
                                                >
                                                    {w.status.replace('_', ' ')}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                                <Link
                                    href={`/work-orders?venue_id=${venue.id}`}
                                    className="text-xs text-muted-foreground hover:text-foreground hover:underline"
                                >
                                    View all work orders
                                </Link>
                            </>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="flex flex-col gap-3 p-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">
                                Recurring work-order templates
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {work_order_templates.length}
                            </span>
                        </div>
                        {work_order_templates.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recurring templates for this venue.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {work_order_templates.map((t) => (
                                    <li
                                        key={t.id}
                                        className="flex items-center justify-between gap-3 rounded-md border border-border p-2"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="font-medium">
                                                {t.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {t.kind?.replace('_', ' ') ??
                                                    '-'}
                                                {t.lookahead_days
                                                    ? ` · materializes ${t.lookahead_days} days out`
                                                    : ''}
                                            </div>
                                        </div>
                                        <span className="text-xs text-muted-foreground">
                                            {describeRrule(t.recurrence_rrule)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div>
                    <Link
                        href={index().url}
                        className="text-sm text-muted-foreground hover:text-foreground"
                    >
                        Back to venues
                    </Link>
                </div>
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

VenuesShow.layout = {
    breadcrumbs: [{ title: 'Venues', href: '/venues' }],
};
